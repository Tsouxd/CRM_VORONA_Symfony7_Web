<?php
namespace App\Controller\Production;

use App\Entity\Commande;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
// On ajoute le 'use' pour TextareaField
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ProductionCommandeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Commande::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInPlural('Gestion de Production')
                    ->setEntityLabelInSingular('Commande')
                    ->setDefaultSort(['dateCommande' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        // === Panneau 1 : Informations Générales (Lecture seule) ===
        yield FormField::addPanel('Informations sur la Commande')->collapsible();
        yield DateTimeField::new('dateCommande', 'Date de Commande')->setFormTypeOption('disabled', true);
        yield AssociationField::new('client', 'Client')->setFormTypeOption('disabled', true);
        yield CollectionField::new('commandeProduits', 'Produits à produire')->setFormTypeOption('disabled', true);

        // === Panneau 2 : Détails PAO (Lecture seule) - C'EST L'AJOUT ===
        yield FormField::addPanel('Détails Techniques (Validés par PAO)')->collapsible();
        yield BooleanField::new('paoFichierOk', 'Fichier OK ?')->setFormTypeOption('disabled', true);
        yield BooleanField::new('paoBatOk', 'BAT Prêt ?')->setFormTypeOption('disabled', true);
        yield BooleanField::new('paoModif1Ok', 'M1 Faite')->setFormTypeOption('disabled', true);
        yield BooleanField::new('paoModif2Ok', 'M2 Faite')->setFormTypeOption('disabled', true);
        yield BooleanField::new('paoModif3Ok', 'M3 Faite')->setFormTypeOption('disabled', true);
        yield TextareaField::new('paoMotifM1', 'Historique Motif 1')->setFormTypeOption('disabled', true);
        yield TextareaField::new('paoMotifM2', 'Historique Motif 2')->setFormTypeOption('disabled', true);
        yield TextareaField::new('paoMotifM3', 'Historique Motif 3')->setFormTypeOption('disabled', true);
        
        // === Panneau 3 : Suivi de Production (Votre logique existante) ===
        yield FormField::addPanel('Suivi de la Production')->collapsible();
        yield ChoiceField::new('statutProduction', 'Statut de production')
            ->setChoices([
                'En attente' => Commande::STATUT_PRODUCTION_ATTENTE,
                'En cours de production' => Commande::STATUT_PRODUCTION_EN_COURS,
                'Prêt pour livraison' => Commande::STATUT_PRODUCTION_POUR_LIVRAISON,
            ])
            ->setFormTypeOption('disabled', true) // AUTOMATIQUE
            ->renderAsBadges([
                Commande::STATUT_PRODUCTION_ATTENTE => 'secondary',
                Commande::STATUT_PRODUCTION_EN_COURS => 'primary',
                Commande::STATUT_PRODUCTION_POUR_LIVRAISON => 'success',
            ]);

        // --- Panneau Livraison avec logique conditionnelle ---
        yield FormField::addPanel('Gestion de la Livraison');
        
        $context = $this->getContext();
        /** @var Commande $commande */
        $commande = $context->getEntity()->getInstance();
        $isProductionFinished = ($commande && $commande->getStatutProduction() === Commande::STATUT_PRODUCTION_POUR_LIVRAISON);

        yield TextField::new('lieuDeLivraison', 'Lieu de Livraison')
            ->setFormTypeOption('disabled', !$isProductionFinished)
            ->setHelp($isProductionFinished ? '' : 'Ce champ sera disponible une fois la production terminée.');

        yield TextField::new('nomLivreur', 'Nom du Livreur')
            //->setFormTypeOption('disabled', !$isProductionFinished)
            ->setHelp($isProductionFinished ? '' : 'Ce champ sera disponible une fois la production terminée.');
            
        yield DateTimeField::new('dateDeLivraison', 'Date de Livraison Prévue');
            //->setFormTypeOption('disabled', !$isProductionFinished);
        yield ChoiceField::new('statutLivraison', 'Statut de Livraison')
            ->setChoices([
                'Prêt pour livraison' => Commande::STATUT_LIVRAISON_ATTENTE,
                'Livrée' => Commande::STATUT_LIVRAISON_LIVREE,
                'Retournée' => Commande::STATUT_LIVRAISON_RETOUR,
                'Annulée' => Commande::STATUT_LIVRAISON_ANNULEE,
            ])
            ->renderAsBadges([
                Commande::STATUT_LIVRAISON_ATTENTE => 'primary',
                Commande::STATUT_LIVRAISON_RETOUR => 'warning',
                Commande::STATUT_LIVRAISON_LIVREE => 'success',
                Commande::STATUT_LIVRAISON_ANNULEE => 'danger',
            ]);
            //->setFormTypeOption('disabled', !$isProductionFinished);
            
        yield BooleanField::new('productionTermineeOk', 'Marquer comme "Production Terminée"')
            ->setHelp('Cochez cette case lorsque la commande est entièrement produite et prête à être livrée. Le statut changera automatiquement.')
            ->setFormTypeOption('attr', ['class' => 'autosubmit-checkbox'])
            ->onlyOnForms();

        yield BooleanField::new('productionTermineeOk', 'Marquer comme "Production Terminée"')
            ->setHelp('Cochez cette case lorsque la commande est entièrement produite et prête à être livrée. Le statut changera automatiquement.')
            ->setFormTypeOption('attr', ['class' => 'autosubmit-checkbox'])
            ->setFormTypeOption('disabled', true) // AUTOMATIQUE
            ->onlyOnIndex();

        // Le script JS pour l'auto-soumission reste à la fin, c'est parfait
        yield FormField::addPanel('')->setHelp(<<<HTML
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    document.querySelectorAll('.autosubmit-checkbox input[type=checkbox]').forEach(checkbox => {
                        checkbox.addEventListener('change', function() {
                            this.closest('form').submit();
                        });
                    });
                });
            </script>
        HTML)->setCssClass('d-none');
    }
}