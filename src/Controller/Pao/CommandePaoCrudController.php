<?php
namespace App\Controller\Pao;

use App\Entity\CommandeProduit;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class CommandePaoCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CommandeProduit::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('commande'),
            AssociationField::new('produit'),
            IntegerField::new('quantite'),
        ];
    }
}