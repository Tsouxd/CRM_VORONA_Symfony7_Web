<?php

namespace App\Controller\Admin;

use App\Entity\Pao;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

class PaoCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Pao::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),

            TextField::new('nom')
                ->setLabel('Nom PAO'),
        ];
    }
}