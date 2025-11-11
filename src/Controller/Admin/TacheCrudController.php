<?php
// src/Controller/Admin/TacheCrudController.php

namespace App\Controller\Admin;

use App\Entity\Tache;
use App\Entity\User; // <-- Assurez-vous d'importer User
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Bundle\SecurityBundle\Security;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class TacheCrudController extends AbstractCrudController
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public static function getEntityFqcn(): string
    {
        return Tache::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        // Si ADMIN ou COMMERCIAL → tout est autorisé
        if ($this->security->isGranted('ROLE_ADMIN') || $this->security->isGranted('ROLE_COMMERCIAL')) {
            return $actions;
        }

        // Sinon (PAO ou PRODUCTION) → désactiver ajout, suppression, modification
        return $actions
            ->disable(Action::NEW, Action::DELETE/*, Action::EDIT*/);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Tâche de suivi')
            ->setEntityLabelInPlural('Suivi des Tâches')
            ->setDefaultSort(['priorite' => 'DESC', 'dateEcheance' => 'ASC'])
            ->setPageTitle('index', 'Suivi des tâches');
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $user = $this->getUser();

        // ADMIN → voit tout
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return $qb;
        }

        // Condition VISIBILITÉ :
        // tache créee par l’utilisateur
        // tache assignée à l’utilisateur

        $qb
            ->andWhere('entity.creePar = :user OR entity.assigneA = :user')
            ->setParameter('user', $user);

        return $qb;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('statut')
            ->add('priorite')
            ->add('assigneA')
            ->add('dateEcheance');
    }

    public function configureFields(string $pageName): iterable
    {
        $user = $this->getUser();
        $tacheEntity = $pageName === Crud::PAGE_EDIT ? $this->getContext()->getEntity()->getInstance() : null;

        // Déterminer si les champs doivent être grisés
        $readonlyExceptStatut = false;

        // PAO / PRODUCTION → tout grisé sauf statut
        if (!$this->security->isGranted('ROLE_ADMIN') && !$this->security->isGranted('ROLE_COMMERCIAL')) {
            $readonlyExceptStatut = true;
        }

        // Commercial assigné par ADMIN → tout grisé sauf statut
        if ($tacheEntity instanceof Tache) {
            $assigne = $tacheEntity->getAssigneA();
            $creePar = $tacheEntity->getCreePar();

            if ($assigne === $user 
                && in_array('ROLE_COMMERCIAL', $user->getRoles(), true)
                && $creePar && in_array('ROLE_ADMIN', $creePar->getRoles(), true)
            ) {
                $readonlyExceptStatut = true;
            }
        }

        // Titre
        yield TextField::new('titre')->setFormTypeOption('disabled', $readonlyExceptStatut);

        // Assigné
        $assigneField = AssociationField::new('assigneA', 'Assignée à')
            ->setQueryBuilder(function (QueryBuilder $qb) use ($pageName) {
                $alias = $qb->getRootAliases()[0];

                // ADMIN → voit tout
                if ($this->security->isGranted('ROLE_ADMIN')) {
                    return $qb;
                }

                // COMMERCIAL
                if ($this->security->isGranted('ROLE_COMMERCIAL')) {
                    // Sur création → ne montrer que PAO et Production
                    if ($pageName === Crud::PAGE_NEW) {
                        $qb->where(sprintf('%s.roles LIKE :role_pao OR %s.roles LIKE :role_prod', $alias, $alias))
                        ->setParameter('role_pao', '%"ROLE_PAO"%')
                        ->setParameter('role_prod', '%"ROLE_PRODUCTION"%');
                    }
                    // Sur édition → rien à changer, le reste est géré par $readonlyExceptStatut
                }

                // PAO / PRODUCTION → juste afficher l'utilisateur assigné actuel
                if (!$this->security->isGranted('ROLE_ADMIN') && !$this->security->isGranted('ROLE_COMMERCIAL')) {
                    $qb->where($alias . ' = :currentUser')
                    ->setParameter('currentUser', $this->getUser());
                }

                return $qb->orderBy(sprintf('%s.username', $alias), 'ASC');
            })
            ->setFormTypeOption('choice_label', function (?User $user) {
                return $user ? $this->getFormattedUserLabel($user) : '';
            })
            ->setFormTypeOption('disabled', $readonlyExceptStatut);

        yield $assigneField;

        // Statut → toujours modifiable pour PAO / Production / commercial assigné par admin
        yield ChoiceField::new('statut')->setChoices([
            'À faire' => Tache::STATUT_A_FAIRE,
            'En cours' => Tache::STATUT_EN_COURS,
            'Bloqué' => Tache::STATUT_BLOQUE,
            'Terminé' => Tache::STATUT_TERMINE,
        ])->renderAsBadges([
            Tache::STATUT_A_FAIRE => 'secondary',
            Tache::STATUT_EN_COURS => 'info',
            Tache::STATUT_BLOQUE => 'warning',
            Tache::STATUT_TERMINE => 'success',
        ]);

        // Priorité, Date d'échéance, Description
        yield ChoiceField::new('priorite')
            ->setChoices([
                'Faible' => Tache::PRIORITE_FAIBLE,
                'Normale' => Tache::PRIORITE_NORMALE,
                'Haute' => Tache::PRIORITE_HAUTE,
                'Urgente' => Tache::PRIORITE_URGENTE,
            ])
            ->renderAsBadges([
                Tache::PRIORITE_FAIBLE => 'secondary',
                Tache::PRIORITE_NORMALE => 'primary',
                Tache::PRIORITE_HAUTE => 'warning',
                Tache::PRIORITE_URGENTE => 'danger',
            ])
            ->setFormTypeOption('disabled', $readonlyExceptStatut);

        yield DateField::new('createdAt', 'Créée le')
            ->setFormat('dd/MM/yyyy')
            ->hideOnForm(); // readonly

        yield DateField::new('dateEcheance', 'Échéance')
            ->setFormat('dd/MM/yyyy')
            ->setFormTypeOption('disabled', $readonlyExceptStatut)
            ->setCustomOption('color', function ($value, Tache $tache) {
                if ($value === null || $tache->getStatut() === Tache::STATUT_TERMINE) return null;
                return $value < new \DateTime() ? 'danger' : null;
            });
        
        yield TextField::new('joursRestants', 'Jours restants')
            ->formatValue(function ($value, Tache $tache) {
                $dateEcheance = $tache->getDateEcheance();
                if (!$dateEcheance) return '-';

                $today = new \DateTimeImmutable();
                $interval = $today->diff($dateEcheance);
                $jours = (int) $interval->format('%r%a'); // différence brute

                if ($jours < 0) {
                    return 'Expiré';
                }

                // Ajout du dernier jour inclusif
                $jours = $jours + 1;

                if ($jours === 1) {
                    return 'Dernier jour pour cette tâche';
                }

                return $jours . ' jours';
            })
            ->hideOnForm();

        yield TextareaField::new('description')
            ->setFormTypeOption('disabled', $readonlyExceptStatut);

        yield AssociationField::new('creePar', 'Créée par')->hideOnForm();
    }

    public function createEntity(string $entityFqcn)
    {
        $tache = new Tache();
        $tache->setCreePar($this->getUser());
        return $tache;
    }

    /**
     * Fonction d'aide pour formater le nom de l'utilisateur avec son rôle.
     * @param User $user
     * @return string
     */
    private function getFormattedUserLabel(User $user): string
    {
        $roleLabel = 'Utilisateur'; // Un libellé par défaut
        $roles = $user->getRoles();

        if (in_array('ROLE_PRODUCTION', $roles, true)) {
            $roleLabel = 'Production';
        } elseif (in_array('ROLE_PAO', $roles, true)) {
            $roleLabel = 'PAO';
        } elseif (in_array('ROLE_COMMERCIAL', $roles, true)) {
            $roleLabel = 'Commercial';
        } elseif (in_array('ROLE_ADMIN', $roles, true)) {
            $roleLabel = 'Admin';
        }

        return sprintf('%s (%s)', $user->getUsername(), $roleLabel);
    }
}