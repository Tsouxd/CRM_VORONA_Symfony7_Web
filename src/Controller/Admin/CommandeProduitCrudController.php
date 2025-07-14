<?php

namespace App\Controller\Admin;

use App\Entity\CommandeProduit;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use Symfony\Component\HttpFoundation\Response; // Potentiellement utile, mais pas obligatoire ici

class CommandeProduitCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CommandeProduit::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('commande'),
            AssociationField::new('produit')
                ->setFormTypeOption('query_builder', function ($repository) {
                    return $repository->createQueryBuilder('p')
                        ->where('p.stock > 0')
                        ->orderBy('p.nom', 'ASC');
                }),
            IntegerField::new('quantite')
                ->setFormTypeOption('attr', ['min' => 1]),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::NEW, fn(Action $action) =>
                $action->setLabel('Ajouter un produit à une commande'));
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof CommandeProduit) {
            return;
        }

        $produit = $entityInstance->getProduit();
        $quantiteCommandee = $entityInstance->getQuantite();

        // --- NOUVEAU : Vérification du stock avant de persister ---
        if ($produit && $produit->getStock() < $quantiteCommandee) {
            // Ajoute un message d'erreur qui sera affiché à l'utilisateur
            $this->addFlash('danger', sprintf(
                'Stock insuffisant pour le produit "%s". Quantité demandée : %d, Stock disponible : %d.',
                $produit->getNom(),
                $quantiteCommandee,
                $produit->getStock()
            ));

            // On ne fait rien d'autre, l'entité ne sera pas sauvegardée.
            return;
        }

        // --- La logique originale va ici, si le stock est OK ---
        if ($produit) {
            $produit->setStock($produit->getStock() - $quantiteCommandee);
            $entityManager->persist($produit);
        }

        parent::persistEntity($entityManager, $entityInstance);

        // Mise à jour du total dans la commande parente
        $commande = $entityInstance->getCommande();
        if ($commande) {
            foreach ($commande->getPaiements() as $paiement) {
                $paiement->updateMontant();
                $entityManager->persist($paiement);
            }
            $entityManager->flush();
        }
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof CommandeProduit) {
            return;
        }

        $produit = $entityInstance->getProduit();
        $newQuantite = $entityInstance->getQuantite();

        // On récupère la quantité originale avant la modification
        $originalEntityData = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);
        $oldQuantite = $originalEntityData['quantite'] ?? 0;

        // --- NOUVEAU : Vérification du stock avant de mettre à jour ---
        // Pour la vérification, on doit calculer le stock disponible "total"
        // en restituant temporairement le stock de cette ligne de commande.
        $stockDisponible = $produit->getStock() + $oldQuantite;

        if ($newQuantite > $stockDisponible) {
            $this->addFlash('danger', sprintf(
                'Stock insuffisant pour le produit "%s". Vous demandez %d, mais seulement %d sont disponibles (en comptant votre commande initiale).',
                $produit->getNom(),
                $newQuantite,
                $stockDisponible
            ));
            return;
        }

        // --- La logique originale va ici, si le stock est OK ---
        $diff = $newQuantite - $oldQuantite;
        if ($produit) {
            $produit->setStock($produit->getStock() - $diff);
            $entityManager->persist($produit);
        }

        parent::updateEntity($entityManager, $entityInstance);

        // Mise à jour du total dans la commande parente
        $commande = $entityInstance->getCommande();
        if ($commande) {
            foreach ($commande->getPaiements() as $paiement) {
                $paiement->updateMontant();
                $entityManager->persist($paiement);
            }
            $entityManager->flush();
        }
    }
    
    // N'oublie pas de gérer la restitution du stock lors de la suppression !
    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof CommandeProduit) {
            return;
        }

        // --- AMÉLIORATION : Restituer le stock du produit supprimé ---
        $produit = $entityInstance->getProduit();
        if ($produit) {
            $produit->setStock($produit->getStock() + $entityInstance->getQuantite());
            $entityManager->persist($produit);
        }
        
        // Le reste de ta logique
        $commande = $entityInstance->getCommande();
        parent::deleteEntity($entityManager, $entityInstance);

        if ($commande) {
            foreach ($commande->getPaiements() as $paiement) {
                $paiement->updateMontant();
                $entityManager->persist($paiement);
            }
            $entityManager->flush();
        }
    }
}