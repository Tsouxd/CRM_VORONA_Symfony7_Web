<?php
namespace App\Controller\Pao;

use App\Entity\Commande;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;

class PaoCommandeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string { return Commande::class; }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInPlural('Gestion de PAO')
                    ->setEntityLabelInSingular('Commande')
                    ->setDefaultSort(['dateCommande' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    // === C'EST LA MÉTHODE LA PLUS IMPORTANTE ===
    /**
     * Cette méthode est appelée par EasyAdmin pour construire la requête
     * qui récupère les éléments à afficher dans la page 'index' (la liste).
     * On va la surcharger pour y ajouter notre filtre de sécurité.
     */
    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        // 1. On récupère le QueryBuilder par défaut d'EasyAdmin
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        // 2. On récupère l'utilisateur actuellement connecté
        $user = $this->getUser();

        // 3. On ajoute notre condition : ne montrer que les commandes
        //    où le champ 'pao' est égal à l'utilisateur connecté.
        // 'entity' est l'alias par défaut pour la table principale dans EasyAdmin.
        $qb->andWhere('entity.pao = :currentUser')
           ->setParameter('currentUser', $user);

        // 4. On retourne le QueryBuilder modifié
        return $qb;
    }

    public function configureFields(string $pageName): iterable
    {
        // Panneau 1: Informations (lecture seule)
        yield FormField::addPanel('Informations Générales')->collapsible();
        yield AssociationField::new('client')->setFormTypeOption('disabled', true);
        yield AssociationField::new('pao', 'PAO en charge')->setFormTypeOption('disabled', true);

        // Panneau 2: Travail du PAO
        yield FormField::addPanel('Suivi de Production PAO')->collapsible();
        yield BooleanField::new('paoFichierOk', 'Fichier Source OK ?')
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('mapped', true)
            ->setFormTypeOption('attr', ['data-ea-ajax' => 'false'])
            ->onlyOnForms(); // dans les formulaires, il est modifiable

        yield BooleanField::new('paoFichierOk', 'Fichier Source OK ?')
            ->setFormTypeOption('disabled', true) // affichage désactivé
            ->onlyOnIndex(); // uniquement dans l’index

        yield BooleanField::new('paoBatOk', 'BAT Préparé ?')
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('mapped', true)
            ->setFormTypeOption('attr', ['data-ea-ajax' => 'false'])
            ->onlyOnForms();

        yield BooleanField::new('paoBatOk', 'BAT Préparé ?')
            ->setFormTypeOption('disabled', true)
            ->onlyOnIndex();

        // Panneau: Suivi des modifications
        yield FormField::addPanel('Suivi des Modifications Effectuées')->collapsible()
            ->setHelp('Cochez la case correspondante UNIQUEMENT après avoir effectué la modification. Le statut sera mis à jour automatiquement.');
        yield BooleanField::new('paoModif1Ok', 'M n°1 Faite')->hideOnIndex();
        yield BooleanField::new('paoModif2Ok', 'M n°2 Faite')->hideOnIndex();
        yield BooleanField::new('paoModif3Ok', 'M n°3 Faite')->hideOnIndex();

        yield BooleanField::new('paoModif1Ok', 'M n°1 Faite')->onlyOnIndex()->setFormTypeOption('disabled', true);
        yield BooleanField::new('paoModif2Ok', 'M n°2 Faite')->onlyOnIndex()->setFormTypeOption('disabled', true);
        yield BooleanField::new('paoModif3Ok', 'M n°3 Faite')->onlyOnIndex()->setFormTypeOption('disabled', true);

        yield TextareaField::new('paoMotifModification', 'Motif de modification à traiter')
            ->setFormTypeOption('disabled', true);

        // Affiche l'historique
        yield TextareaField::new('paoMotifM1', 'Historique Motif 1')->setFormTypeOption('disabled', true);
        yield TextareaField::new('paoMotifM2', 'Historique Motif 2')->setFormTypeOption('disabled', true);
        yield TextareaField::new('paoMotifM3', 'Historique Motif 3')->setFormTypeOption('disabled', true);

        // Panneau: Validation (le PAO voit et peut resoumettre)
        yield FormField::addPanel('Cycle de Validation')->collapsible();
        
        yield ChoiceField::new('statutPao', 'Statut PAO')
            ->setFormTypeOption('disabled', true)
            ->setChoices([
                'En attente' => Commande::STATUT_PAO_ATTENTE,
                'En cours' => Commande::STATUT_PAO_EN_COURS,
                'Fait (BAT Validé)' => Commande::STATUT_PAO_FAIT,
                'Modification requise' => Commande::STATUT_PAO_MODIFICATION,
            ])
            ->renderAsBadges([
                Commande::STATUT_PAO_ATTENTE => 'secondary',
                Commande::STATUT_PAO_EN_COURS => 'primary',
                Commande::STATUT_PAO_FAIT => 'success',
                Commande::STATUT_PAO_MODIFICATION => 'danger',
            ]);
        yield ChoiceField::new('paoBatValidation', 'Statut du BAT')
            ->setChoices([
                'En attente de validation' => Commande::BAT_EN_ATTENTE,
                'Modification demandée' => Commande::BAT_MODIFICATION,
                'Validé pour production' => Commande::BAT_PRODUCTION,
            ])
            ->renderAsBadges([
                Commande::BAT_EN_ATTENTE => 'secondary',
                Commande::BAT_MODIFICATION => 'danger',
                Commande::BAT_PRODUCTION => 'success',
            ])
            // Le PAO n'a plus à le faire manuellement, on désactive le champ.
            ->setFormTypeOption('disabled', true)
            ->setHelp("Ce statut se mettra à jour automatiquement lorsque vous cocherez une case 'Modif Faite'.");

        yield FormField::addPanel('Suivi de Production')->collapsible();
        yield ChoiceField::new('statutProduction', 'Statut de la Production')
            ->setChoices([ // Il faut lister tous les choix pour qu'EasyAdmin sache comment l'afficher
                'En attente' => Commande::STATUT_PRODUCTION_ATTENTE,
                'En cours de production' => Commande::STATUT_PRODUCTION_EN_COURS,
                'Prêt pour livraison' => Commande::STATUT_PRODUCTION_POUR_LIVRAISON,
            ])
            ->setFormTypeOption('disabled', true) // Lecture seule
            ->renderAsBadges([
                Commande::STATUT_PRODUCTION_ATTENTE => 'secondary',
                Commande::STATUT_PRODUCTION_EN_COURS => 'primary',
                Commande::STATUT_PRODUCTION_POUR_LIVRAISON => 'success',
            ]);
    }
}