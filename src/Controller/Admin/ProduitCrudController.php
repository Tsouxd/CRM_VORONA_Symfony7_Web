<?php

namespace App\Controller\Admin;

use App\Entity\Produit;
use App\Entity\Fournisseur;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;

class ProduitCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Produit::class;
    }

    
    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            ->addCssFile('assets/css/admin_custom.css');
    }
    
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Liste des Produits')
            ->setPageTitle('new', 'Ajouter un Produit')
            ->setPageTitle('edit', 'Modifier un Produit')
            ->setPageTitle('detail', 'Détails du Produit')
            ->setEntityLabelInSingular('Produit')
            ->setEntityLabelInPlural('Produits')
            ->overrideTemplate('crud/index', 'admin/produit/index.html.twig');
    }
    
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('nom', 'Nom du produit'),
            AssociationField::new('fournisseur', 'Fournisseur')
                ->hideOnIndex()
                ->autocomplete()
                ->setFormTypeOption('required', false),

            MoneyField::new('prix')
                ->setCurrency('MGA')
                // On dit au champ de ne pas diviser par 100. L'entier stocké est la valeur affichée.
                ->setFormTypeOption('divisor', 1)
                ->setNumDecimals(0),
                
            NumberField::new('stock')
                ->setCustomOption('renderCallback', function ($value) {
                    if ($value < self::SEUIL_FAIBLE_STOCK) {
                        return '<span class="badge bg-warning">' . $value . '</span>';
                    }
                    return $value;
                }),
        ];
    }
}
