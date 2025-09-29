<?php
namespace App\Controller\Admin;

use App\Entity\Devis;
use App\Form\DevisLigneType;
use App\Form\ClientOrNewClientForDevisType;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use Symfony\Component\HttpFoundation\Response;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField; // <- NOUVEAU
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;       // <- NOUVEAU
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;     // <- NOUVEAU
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;

class DevisCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Devis::class;
    }

    public function configureFields(string $pageName): iterable
    {
        // Affichage sur index/detail
        yield AssociationField::new('client')->hideOnForm();

        // Logique pour le formulaire de création
        // SI on est sur la page de CRÉATION (new)
        if (Crud::PAGE_NEW === $pageName) {
            yield FormField::addPanel('Informations du Client')
            ->setHelp('Choisissez un client existant ou créez-en un nouveau.');

            yield Field::new('clientSelector', 'Client')
                ->setFormType(ClientOrNewClientForDevisType::class)
                ->setFormTypeOptions([
                    'label' => false,
                    'mapped' => false, 
                ])
                ->setRequired(true);
        }
        // Logique pour le formulaire d'édition
        if (Crud::PAGE_EDIT === $pageName) {
            yield FormField::addPanel('Informations du Client');
            yield AssociationField::new('client', 'Client');
                //->setFormTypeOption('disabled', true); // Grise le champ pour qu'il ne soit pas modifiable
        }

        // --- Panneau Lignes de Devis (Saisie Manuelle) ---
        yield FormField::addPanel('Détails et Validation du Devis');
        yield CollectionField::new('lignes', 'Produits')
            ->setEntryType(DevisLigneType::class)
            ->setEntryIsComplex(true)
            ->allowAdd(true)
            ->allowDelete(true)
            ->setFormTypeOptions(['by_reference' => false]);

        yield BooleanField::new('batOk', 'BAT OK ?')
            ->setHelp('Cochez cette case pour accepter le devis et le passer en production. Le statut changera automatiquement.')
            ->onlyOnForms()
            ->setFormTypeOption('attr', ['class' => 'autosubmit-checkbox']); // Pour l'auto-soumission

        yield BooleanField::new('batOk', 'BAT OK ?')
            ->setHelp('Cochez cette case pour accepter le devis et le passer en production. Le statut changera automatiquement.')
            ->onlyOnIndex()
            ->setFormTypeOption('disabled', true)
            ->setFormTypeOption('attr', ['class' => 'autosubmit-checkbox']); // Pour l'auto-soumission


        yield AssociationField::new('pao', 'PAO en charge');
        yield TextField::new('modeDePaiement', 'Mode de paiement');
        yield ChoiceField::new('statut', 'Statut du devis')
            ->setChoices([
                'Envoyé' => Devis::STATUT_ENVOYE,
                'BAT/Production' => Devis::STATUT_BAT_PRODUCTION,
                'Relance' => Devis::STATUT_RELANCE,
                'Perdu' => Devis::STATUT_PERDU,
            ])
            ->renderAsBadges([
                Devis::STATUT_ENVOYE => 'primary',
                Devis::STATUT_BAT_PRODUCTION => 'success',
                Devis::STATUT_RELANCE => 'warning',
                Devis::STATUT_PERDU => 'danger',
            ]);
        
        // --- Total (calculé par JS) ---
        yield MoneyField::new('total', 'Total')
            ->setCurrency('MGA')
            ->setNumDecimals(0)
            ->setFormTypeOption('divisor', 1)
            ->setFormTypeOption('attr', ['readonly' => true]);

        yield DateTimeField::new('dateCreation', 'Date de création')->hideOnForm();

        // ✅ Injection du JS directement dans EasyAdmin via un champ invisible
        yield FormField::addPanel('')
            ->onlyOnForms()
            ->setHelp(<<<HTML
                <script>
                    function initCommandeFormScripts() {
                        // --- Gestion du choix client existant/nouveau ---
                        const choiceRadios = document.querySelectorAll(".client-choice-radio input[type=radio]");
                        const existingBlock = document.querySelector(".existing-client-block");
                        const newBlock = document.querySelector(".new-client-block");

                        function updateClientChoiceVisibility() {
                            const selected = document.querySelector(".client-choice-radio input[type=radio]:checked");
                            if (!selected) return;
                            if (existingBlock && newBlock) {
                                if (selected.value === "existing") {
                                    existingBlock.style.display = "block";
                                    newBlock.style.display = "none";
                                } else if (selected.value === "new") {
                                    existingBlock.style.display = "none";
                                    newBlock.style.display = "block";
                                }
                            }
                        }

                        choiceRadios.forEach(radio => {
                            radio.addEventListener("change", updateClientChoiceVisibility);
                        });

                        // --- Gestion du type de client (particulier/professionnel) ---
                        const typeSelector = document.querySelector(".client-type-selector");
                        const particulierFields = document.querySelectorAll(".particulier-field");
                        const professionnelFields = document.querySelectorAll(".professionnel-field");

                        function updateClientTypeVisibility() {
                            if (!typeSelector) return;
                            const selectedType = typeSelector.querySelector("input[type=radio]:checked");
                            if (!selectedType) return;

                            particulierFields.forEach(field => {
                                field.style.display = selectedType.value === "particulier" ? "block" : "none";
                            });
                            professionnelFields.forEach(field => {
                                field.style.display = selectedType.value === "professionnel" ? "block" : "none";
                            });
                        }

                        if (typeSelector) {
                            typeSelector.addEventListener("change", updateClientTypeVisibility);
                        }

                        // --- Gestion du choix produit existant/nouveau ---
                        const choiceRadios2 = document.querySelectorAll(".product-choice-radio input[type=radio]");
                        const existingBlock2 = document.querySelector(".existing-product-block");
                        const newBlock2 = document.querySelector(".new-product-block");

                        function updateProductChoiceVisibility() {
                            const selected = document.querySelector(".product-choice-radio input[type=radio]:checked");
                            if (!selected) return;
                            if (existingBlock2 && newBlock2) {
                                if (selected.value === "existing") {
                                    existingBlock2.style.display = "block";
                                    newBlock2.style.display = "none";
                                } else if (selected.value === "new") {
                                    existingBlock2.style.display = "none";
                                    newBlock2.style.display = "block";
                                }
                            }
                        }

                        choiceRadios2.forEach(radio => {
                            radio.addEventListener("change", updateProductChoiceVisibility);
                        });

                        // --- Initialisation ---
                        updateClientChoiceVisibility();
                        updateClientTypeVisibility();
                        updateProductChoiceVisibility();
                    }

                    // Initialisation sur chargement DOM et rechargements Turbo
                    document.addEventListener("DOMContentLoaded", initCommandeFormScripts);
                    document.addEventListener("turbo:load", initCommandeFormScripts);
                </script>
            HTML);

        // --- Script JavaScript pour les calculs automatiques ---
        yield FormField::addPanel('')->setHelp(<<<HTML
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const container = document.querySelector('#Devis_lignes');

                    function updateGrandTotal() {
                        let grandTotal = 0;
                        container.querySelectorAll('.devis-prix-total').forEach(totalInput => {
                            grandTotal += parseFloat(totalInput.value) || 0;
                        });
                        document.querySelector('#Devis_total').value = grandTotal;
                    }

                    function updateLine(lineElement) {
                        const quantiteInput = lineElement.querySelector('.devis-quantite');
                        const prixUnitaireInput = lineElement.querySelector('.devis-prix-unitaire');
                        const prixTotalInput = lineElement.querySelector('.devis-prix-total');

                        const quantite = parseInt(quantiteInput.value) || 0;
                        const prixUnitaire = parseFloat(prixUnitaireInput.value) || 0;

                        prixTotalInput.value = (quantite * prixUnitaire).toFixed(0);
                        updateGrandTotal();
                    }

                    function attachListeners(lineElement) {
                        lineElement.querySelector('.devis-quantite').addEventListener('input', () => updateLine(lineElement));
                        lineElement.querySelector('.devis-prix-unitaire').addEventListener('input', () => updateLine(lineElement));
                    }
                    
                    // Pour les lignes existantes et les nouvelles
                    container.addEventListener('ea.collection.item-added', (e) => attachListeners(e.detail.item));
                    container.querySelectorAll('.form-widget-compound').forEach(attachListeners);
                    
                    // Pour la suppression
                    container.addEventListener('ea.collection.item-removed', updateGrandTotal);
                    
                    updateGrandTotal(); // Calcul initial

                    document.querySelectorAll('.autosubmit-checkbox input[type=checkbox]').forEach(checkbox => {
                        checkbox.addEventListener('change', function() {
                            this.closest('form').submit();
                        });
                    });
                });
            </script>
        HTML)->setCssClass('d-none');
    }

    // --- Recalculs côté serveur (sécurité) ---
    private function recalculateTotals(Devis $devis)
    {
        $total = 0;
        foreach ($devis->getLignes() as $ligne) {
            $prixTotalLigne = ($ligne->getQuantite() ?? 0) * ($ligne->getPrixUnitaire() ?? 0);
            $ligne->setPrixTotal($prixTotalLigne);
            $total += $prixTotalLigne;
        }
        $devis->setTotal($total);
    }
    
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Devis) return;
        $this->recalculateTotals($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Devis) return;
        $this->recalculateTotals($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function configureActions(Actions $actions): Actions
    {
        $exportPdf = Action::new('exportPdf', 'Exporter PDF', 'fa fa-file-pdf')
            ->linkToCrudAction('exportPdfAction')
            ->setCssClass('btn btn-primary')
            ->setHtmlAttributes([
                'target' => '_blank', // <- ouvre dans un nouvel onglet
            ]);

        return $actions
            ->add(Crud::PAGE_INDEX, $exportPdf)
            ->add(Crud::PAGE_DETAIL, $exportPdf);
    }

    public function exportPdfAction(AdminUrlGenerator $adminUrlGenerator, EntityManagerInterface $entityManager): Response
{
    $id = $this->getContext()->getRequest()->query->get('entityId');

    // Récupération via EntityManager
    $devis = $entityManager->getRepository(Devis::class)->find($id);

    if (!$devis) {
        throw $this->createNotFoundException("Devis non trouvé");
    }

    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $dompdf = new Dompdf($options);

    $html = $this->renderView('devis/pdf.html.twig', [
        'devis' => $devis,
    ]);

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return new Response(
        $dompdf->output(),
        200,
        [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="devis-'.$devis->getId().'.pdf"',
        ]
    );
}

}
