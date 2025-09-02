<?php
// src/Controller/Admin/UserRequestCrudController.php
namespace App\Controller\Admin;

use App\Entity\UserRequest;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UserRequestCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return UserRequest::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Demande de Compte')
            ->setEntityLabelInPlural('Demandes de Comptes')
            ->setSearchFields(['username', 'createdAt']);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('username'),
            TextField::new('password')->setHelp('Mot de passe suggéré par l\'utilisateur. L\'administrateur doit l\'encoder lors de la création réelle.'),
            DateTimeField::new('createdAt')->setLabel('Date de la demande'),
            // Vous pourriez ajouter un champ 'status' (ex: 'pending', 'approved', 'rejected')
            // et un champ 'notes' pour l'administrateur.
        ];
    }

    // Vous pouvez personnaliser les actions ici.
    // Par exemple, désactiver la création directe via EasyAdmin pour les demandes
    // car elles sont créées via le formulaire public.
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW) // Les demandes sont créées par le formulaire public
            ->add(Crud::PAGE_INDEX, Action::DETAIL) // Permet de voir les détails
            // Vous pourriez ajouter une action personnalisée "Créer Utilisateur"
            // qui prend les infos de UserRequest et crée un User réel.
        ;
    }
}