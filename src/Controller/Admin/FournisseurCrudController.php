<?php

namespace App\Controller\Admin;

use App\Entity\Fournisseur;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

class FournisseurCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Fournisseur::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural("Fournisseurs")
            ->setEntityLabelInSingular("Fournisseur")
            ->setPageTitle("index", "Liste des fournisseurs inscrits au programme");
    }

    /*public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(CRUD::PAGE_INDEX, 'detail'); // Vous pouvez également conserver d'autres actions ici
    }*/

    /*
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id'),
            TextField::new('title'),
            TextEditorField::new('description'),
        ];
    }
    */
}
