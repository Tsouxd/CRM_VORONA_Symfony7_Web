<?php
namespace App\Controller\Production;

use App\Entity\Commande;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;

class ProductionCommandeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Commande::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInPlural('Commandes à produire')
                    ->setEntityLabelInSingular('Commande')
                    ->setDefaultSort(['dateCommande' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW) 
            // Ajoute le bouton "voir" sur la page index
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield DateTimeField::new('dateCommande', 'Date de Commande')
            ->hideOnForm();

        yield AssociationField::new('client', 'Client')
            ->hideOnForm();

        yield CollectionField::new('commandeProduits', 'Produits commandés')
            ->useEntryCrudForm(CommandeProduitCrudController::class);

        yield ChoiceField::new('statut', 'Statut de production')
            ->setChoices([
                'En attente' => 'en attente',
                'En cours' => 'en cours',
                'Livrée' => 'livrée',
            ])
            ->renderAsBadges([
                'en attente' => 'secondary',
                'en cours' => 'primary',
                'livrée' => 'success',
            ]);
    }
}