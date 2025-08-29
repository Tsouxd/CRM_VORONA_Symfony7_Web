<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\PasswordField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

class UserCrudController extends AbstractCrudController
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural("Utilisateurs")
            ->setEntityLabelInSingular("Utilisateur")
            ->setPageTitle("index", "Liste des utilisateurs inscrits au programme");
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            return;
        }

        $roles = $entityInstance->getRoles();
        $entityInstance->setRoles([reset($roles)]);

        if ($plainPassword = $entityInstance->getPlainPassword()) {
            $hashedPassword = $this->passwordHasher->hashPassword($entityInstance, $plainPassword);
            $entityInstance->setPassword($hashedPassword);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            return;
        }

        $roles = $entityInstance->getRoles();
        $entityInstance->setRoles([reset($roles)]);

        if ($plainPassword = $entityInstance->getPlainPassword()) {
            $hashedPassword = $this->passwordHasher->hashPassword($entityInstance, $plainPassword);
            $entityInstance->setPassword($hashedPassword);
        } else {
            $originalData = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);
            $entityInstance->setPassword($originalData['password']);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFields(string $pageName): iterable
    {
        $fields = [
            IdField::new('id')
                ->hideOnForm(),
            TextField::new('username', 'Nom d\'utilisateur')
                ->setRequired(true)
                ->setHelp('Le nom d\'utilisateur doit être unique.'),
        ];

        if ($pageName === 'new' || $pageName === 'edit') {
            $fields[] = ChoiceField::new('roles', 'Rôle de l\'utilisateur.')
                ->setHelp('Sélectionnez un seul rôle pour l\'utilisateur.')
                ->setChoices([
                    'Administrateur' => 'ROLE_ADMIN',
                    'Commercial' => 'ROLE_COMMERCIAL',
                    'Production' => 'ROLE_PRODUCTION',
                ])
                ->allowMultipleChoices(true) // nécessaire car roles est un array
                ->renderExpanded(true) // boutons radio
                ->setFormTypeOptions([
                    'by_reference' => false,
                ]);
        } else {
            $fields[] = ArrayField::new('roles', 'Rôle')
                ->formatValue(function ($value, $entity) {
                    // Affiche le premier rôle utilisateur avec un libellé personnalisé
                    if (is_array($value)) {
                        return implode(', ', array_map(function ($role) {
                            return match ($role) {
                                'ROLE_ADMIN' => 'Administrateur',
                                'ROLE_COMMERCIAL' => 'Commercial',
                                'ROLE_PRODUCTION' => 'Production',
                                default => $role,
                            };
                        }, $value));
                    }

                    return $value;
                });
        }

        if ($pageName === 'new') {
            $fields[] = TextField::new('plainPassword', 'Mot de passe')
                ->setRequired(true)
                ->setFormTypeOption('attr', ['type' => 'password'])
                ->setHelp('Le mot de passe doit contenir au moins 8 caractères.');
        } elseif ($pageName === 'edit') {
            $fields[] = TextField::new('plainPassword', 'Nouveau mot de passe')
                ->setRequired(false)
                ->setFormTypeOption('attr', ['type' => 'password'])
                ->setHelp('Laissez vide si vous ne voulez pas changer le mot de passe.');
        }

        return $fields;
    }
}
