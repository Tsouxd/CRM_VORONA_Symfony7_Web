<?php
namespace App\Controller\Admin;

use App\Entity\CommandeProduit;
use App\Entity\Paiement;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

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

        // Mise à jour du stock produit (optionnel, selon ta logique)
        $produit = $entityInstance->getProduit();
        if ($produit) {
            $produit->setStock($produit->getStock() - $entityInstance->getQuantite());
            $entityManager->persist($produit);
        }

        parent::persistEntity($entityManager, $entityInstance);

        // Mise à jour du total dans la commande parente
        $commande = $entityInstance->getCommande();
        if ($commande) {
            // Met à jour le montant du paiement
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

        // Garde ta logique stock actuelle
        $oldCommandeProduit = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);

        if (!isset($oldCommandeProduit['quantite'])) {
            parent::updateEntity($entityManager, $entityInstance);
            return;
        }

        $oldQuantite = $oldCommandeProduit['quantite'];
        $newQuantite = $entityInstance->getQuantite();
        $diff = $newQuantite - $oldQuantite;

        $produit = $entityInstance->getProduit();
        if ($produit) {
            $produit->setStock($produit->getStock() - $diff);
            $entityManager->persist($produit);
        }

        parent::updateEntity($entityManager, $entityInstance);

        // Mise à jour du total dans la commande parente
        $commande = $entityInstance->getCommande();
        if ($commande) {
                // Met à jour le montant du paiement
                foreach ($commande->getPaiements() as $paiement) {
                    $paiement->updateMontant();
                    $entityManager->persist($paiement);
                }

                $entityManager->flush();
        }
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof CommandeProduit) {
            return;
        }

        $commande = $entityInstance->getCommande();

        parent::deleteEntity($entityManager, $entityInstance);

        if ($commande) {
            // Met à jour le montant du paiement
            foreach ($commande->getPaiements() as $paiement) {
                $paiement->updateMontant();
                $entityManager->persist($paiement);
            }

            $entityManager->flush();
        }
    }
}