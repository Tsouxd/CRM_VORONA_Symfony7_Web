<?php
namespace App\Controller\Pao;

use App\Entity\Commande;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;

class PaoCommandeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Commande::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInPlural('Gestion de PAO')
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
        
        yield AssociationField::new('pao', 'Nom du PAO')
            ->setCrudController(PaoCrudController::class) // Si tu as un CRUD pour PAO
            ->setRequired(false) // Nullable
            ->setFormTypeOption('disabled', true);
            
        yield AssociationField::new('client', 'Client')
            ->hideOnForm();

        yield CollectionField::new('commandeProduits', 'Produits commandés')
            ->useEntryCrudForm(CommandePaoCrudController::class)
            ->setFormTypeOption('disabled', true)
            ->hideOnForm();

        yield ChoiceField::new('statutPao', 'Statut de PAO')
                ->setChoices([
                    'En attente' => 'en attente',
                    'En cours' => 'en cours',
                    'Fait' => 'fait',
                ])
                ->renderAsBadges([
                    'en attente' => 'secondary',
                    'en cours' => 'primary',
                    'fait' => 'success',
                ]);
    }
}