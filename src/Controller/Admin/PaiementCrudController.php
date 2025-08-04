<?php

namespace App\Controller\Admin;

use App\Entity\Paiement;
use App\Entity\Commande;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

class PaiementCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Paiement::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural("Paiements")
            ->setEntityLabelInSingular("Paiement")
            ->setPageTitle("index", "Liste des paiements au programme");
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(CRUD::PAGE_INDEX, 'detail'); // Vous pouvez également conserver d'autres actions ici
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Paiement) {
            return;
        }

        $commande = $entityInstance->getCommande();

        // ✅ 1. Appliquer le statut à la commande
        if ($commande !== null) {
            if ($entityInstance->getStatut() === 'annulée') {
                $commande->setStatut('annulée');
            } elseif ($entityInstance->getStatut() === 'en attente') {
                $commande->setStatut('en attente');
            } elseif (in_array($entityInstance->getStatut(), ['en cours', 'payée'])) {
                $commande->setStatut('en cours'); // ou 'payée'
            }

            $entityManager->persist($commande);
        }

        // ✅ 2. Mise à jour du montant DIRECTEMENT sur ce paiement
        if (in_array($entityInstance->getStatut(), ['en cours', 'payée'])) {
            $entityInstance->updateMontant();
        }

        // ✅ 3. Persist du paiement avec montant mis à jour
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Paiement) {
            return;
        }

        // Si le statut du paiement est "annulée", on annule la commande associée
        if ($entityInstance->getStatut() === 'annulée') {
            $commande = $entityInstance->getCommande();

            if ($commande !== null) {
                $commande->setStatut('annulée');
                $entityManager->persist($commande);
            }
        } else if ($entityInstance->getStatut() === 'en attente') {
            // Si le statut du paiement est "payée", on marque la commande comme payée
            $commande = $entityInstance->getCommande();

            if ($commande !== null) {
                $commande->setStatut('en attente');
                $entityManager->persist($commande);
            }
        } else if ($entityInstance->getStatut() === 'en cours') {
            // Si le statut du paiement est "payée", on marque la commande comme payée
            $commande = $entityInstance->getCommande();

            if ($commande !== null) {
                $commande->setStatut('en cours');
                $entityManager->persist($commande);
            }
        } else if ($entityInstance->getStatut() === 'payée') {
            // Si le statut du paiement est "payée", on marque la commande comme payée
            $commande = $entityInstance->getCommande();

            if ($commande !== null) {
                $commande->setStatut('en cours');
                $entityManager->persist($commande);
            }
        }

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

        // Ne pas oublier de persister le paiement lui-même
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function configureFields(string $pageName): iterable
    {
        yield DateTimeField::new('datePaiement');
        yield AssociationField::new('commande');
        yield MoneyField::new('montant')
            ->setCurrency('MGA')
            ->setStoredAsCents(false)
            ->setDisabled(true) // ❌ Désactivé à la saisie, affiché uniquement

            ->onlyOnForms(); // affiché uniquement dans les formulaires

        yield MoneyField::new('montant')
            ->setCurrency('MGA')
            ->setStoredAsCents(false)
            ->onlyOnIndex(); // affiché dans la liste

        yield ChoiceField::new('statut')
            ->setChoices([
                'En attente' => 'en attente',
                'En cours' => 'en cours',
                'Payée' => 'payée',
                'Annulée' => 'annulée',
            ])
            ->renderAsBadges([
                'en attente' => 'warning',
                'en cours' => 'info',
                'payée' => 'success',
                'annulée' => 'danger',
            ]);
    }

}
