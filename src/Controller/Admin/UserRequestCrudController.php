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
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;  

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
            TextField::new('username', 'Nom d’utilisateur demandé')->setFormTypeOption('disabled', true),
            TextField::new('password', 'Mot de passe envisagé')->setHelp('Mot de passe suggéré par l\'utilisateur. L\'administrateur doit l\'encoder lors de la création réelle.')->setFormTypeOption('disabled', true),
            DateTimeField::new('createdAt')->setLabel('Date de la demande')->setFormTypeOption('disabled', true),
                    // ✅ Voici comment afficher le statut sous forme de choix
            TextField::new('roleDemander', 'Rôle souhaité du Demandeur')->setFormTypeOption('disabled', true),
            ChoiceField::new('status', 'Statut de validation')
                ->setChoices([
                    'Pas encore fait' => 'pas encore fait',
                    'Fait' => 'fait',
                ])
                ->renderAsBadges([
                    'pas encore fait' => 'warning', // Affiche un badge jaune
                    'fait' => 'success',      // Affiche un badge vert
                ]),
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