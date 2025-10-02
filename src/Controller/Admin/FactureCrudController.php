<?php
namespace App\Controller\Admin;

use App\Entity\Facture;
use App\Form\FactureLigneType;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use Symfony\Component\HttpFoundation\Response;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

class FactureCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Facture::class;
    }
    
    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('client', 'Client'),

            CollectionField::new('lignes', 'Produits')
                ->setEntryType(FactureLigneType::class)
                ->setEntryIsComplex(true)
                ->allowAdd(true)
                ->allowDelete(true)
                ->setFormTypeOptions(['by_reference' => false]),

            MoneyField::new('fraisLivraison', 'Frais de livraison')
                ->setCurrency('MGA')
                ->setNumDecimals(0) 
                ->setFormTypeOption('divisor', 1)
                ->setFormTypeOption('attr', ['class' => 'facture-frais-livraison']),

            TextField::new('livreur', 'Nom du livreur'),

            FormField::addPanel('Conditions de Paiement'),

            ChoiceField::new('modeDePaiement', 'Mode de paiement')
                ->setChoices([
                    'Chèque' => 'Chèque',
                    'Espèce' => 'Espèce',
                    'Virement bancaire' => 'Virement bancaire',
                    'Carte bancaire' => 'Carte bancaire',
                    'Mobile Money' => 'Mobile Money',
                ])
                ->setHelp('Choisissez le moyen de paiement principal.'),

            TextField::new('detailsPaiement', 'Détails / Références')
                ->setHelp('Ex: MVola, numéro de chèque, référence de virement...'),

            ChoiceField::new('methodePaiement', 'Méthode de paiement')
                ->setChoices([
                    '50% à la commande, 50% à la livraison' => '50% commande, 50% livraison',
                    '100% à la livraison' => '100% livraison',
                    '30 jours après réception de la facture' => '30 jours fin de mois',
                    '100% à la commande' => '100% commande',
                ])
                ->setHelp('Choisissez les conditions de règlement.'),

            MoneyField::new('total', 'Total')
                ->setCurrency('MGA')
                ->setNumDecimals(0) 
                ->setFormTypeOption('divisor', 1)
                ->setFormTypeOption('disabled', 'disabled')
                ->setFormTypeOption('attr', ['class' => 'facture-total-general']),

            DateTimeField::new('dateCreation', 'Date de création')->hideOnForm(),

            // === Script dynamique ===
            FormField::addPanel('')
                ->setHelp(<<<HTML
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            // --- Calcul total général ---
                            const updateFactureTotal = () => {
                                let total = 0;

                                // Additionner les prix totaux de chaque ligne
                                document.querySelectorAll('[id$=_prixTotal]').forEach(input => {
                                    const val = parseFloat(input.value || 0);
                                    if (!isNaN(val)) total += val;
                                });

                                // Ajouter les frais de livraison
                                const fraisLivraisonInput = document.querySelector('.facture-frais-livraison');
                                if (fraisLivraisonInput) {
                                    const frais = parseFloat(fraisLivraisonInput.value || 0);
                                    if (!isNaN(frais)) total += frais;
                                }

                                // Afficher le total général
                                const totalGeneralInput = document.querySelector('.facture-total-general');
                                if (totalGeneralInput) {
                                    totalGeneralInput.value = total.toFixed(0);
                                }
                            };

                            // --- Mise à jour prix unitaire & total d'une ligne ---
                            const updateLine = (container) => {
                                const select = container.querySelector('select');
                                const qtyInput = container.querySelector('[id$=_quantite]');
                                const unitInput = container.querySelector('[id$=_prixUnitaire]');
                                const totalInput = container.querySelector('[id$=_prixTotal]');

                                if (!select || !qtyInput || !unitInput || !totalInput) return;

                                const selectedOption = select.options[select.selectedIndex];
                                const prix = parseFloat(selectedOption.getAttribute('data-prix') || 0);
                                const quantite = parseInt(qtyInput.value || 0);

                                if (!isNaN(prix)) {
                                    unitInput.value = prix.toFixed(0);
                                }
                                if (!isNaN(prix) && !isNaN(quantite)) {
                                    totalInput.value = (prix * quantite).toFixed(0);
                                }

                                updateFactureTotal();
                            };

                            // --- Attacher les événements aux lignes existantes ---
                            const attachListeners = (container) => {
                                const select = container.querySelector('select');
                                const qtyInput = container.querySelector('[id$=_quantite]');

                                if (select) {
                                    select.addEventListener('change', () => updateLine(container));
                                }
                                if (qtyInput) {
                                    qtyInput.addEventListener('input', () => updateLine(container));
                                }
                            };

                            document.querySelectorAll('.form-widget-compound').forEach(attachListeners);

                            // --- Gérer l’ajout de nouvelles lignes ---
                            const addButton = document.querySelector('.field-collection-add-button');
                            if (addButton) {
                                addButton.addEventListener('click', function() {
                                    setTimeout(() => {
                                        const newContainers = document.querySelectorAll('.form-widget-compound:not(.listening)');
                                        newContainers.forEach(container => {
                                            container.classList.add('listening');
                                            attachListeners(container);
                                        });
                                    }, 200);
                                });
                            }

                            // --- Frais de livraison change ---
                            const fraisLivraisonInput = document.querySelector('.facture-frais-livraison');
                            if (fraisLivraisonInput) {
                                fraisLivraisonInput.addEventListener('input', updateFactureTotal);
                            }
                        });
                    </script>
                HTML
            )->onlyOnForms(),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Facture) return;

        $total = $entityInstance->getFraisLivraison();

        foreach ($entityInstance->getLignes() as $ligne) {
            $produit = $ligne->getProduit();
            if ($produit) {
                $ligne->setPrixUnitaire($produit->getPrix());
                $ligne->setPrixTotal($produit->getPrix() * $ligne->getQuantite());
                $ligne->setFacture($entityInstance);
                $total += $ligne->getPrixTotal();
            }
        }

        $entityInstance->setTotal($total);

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Facture) {
            $total = $entityInstance->getFraisLivraison();

            foreach ($entityInstance->getLignes() as $ligne) {
                $produit = $ligne->getProduit();
                if ($produit) {
                    $ligne->setPrixUnitaire($produit->getPrix());
                    $ligne->setPrixTotal($produit->getPrix() * $ligne->getQuantite());
                    $ligne->setFacture($entityInstance);
                    $total += $ligne->getPrixTotal();
                }
            }

            $entityInstance->setTotal($total);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function configureActions(Actions $actions): Actions
    {
        $exportPdf = Action::new('exportPdf', 'Exporter PDF', 'fa fa-file-pdf')
            ->linkToUrl(function($entity) {
                $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
                return $adminUrlGenerator
                    ->setController(self::class)
                    ->setAction('exportPdfAction')
                    ->set('entityId', $entity->getId())
                    ->generateUrl();
            })
            ->setHtmlAttributes(['target' => '_blank'])
            ->setCssClass('btn btn-primary');

        return $actions
            ->add(Crud::PAGE_INDEX, $exportPdf)
            ->add(Crud::PAGE_DETAIL, $exportPdf);
    }

    public function exportPdfAction(AdminUrlGenerator $adminUrlGenerator, EntityManagerInterface $entityManager): Response
    {
        $id = $this->getContext()->getRequest()->query->get('entityId');

        $facture = $entityManager->getRepository(Facture::class)->find($id);
        if (!$facture) {
            throw $this->createNotFoundException("Facture non trouvée");
        }

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);

        $html = $this->renderView('facture/pdf.html.twig', ['facture' => $facture]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="facture-'.$facture->getId().'.pdf"',
            ]
        );
    }
}
