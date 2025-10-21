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
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class FactureCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Facture::class;
    }
    
    public function configureFields(string $pageName): iterable
    {
        // ✅ CHAMP COMMANDE — création de facture à partir d'une commande
        if (Crud::PAGE_NEW === $pageName) {
            yield FormField::addPanel('Source de la Facture (Optionnel)')->collapsible();

            yield AssociationField::new('commande', 'Créer à partir d\'une Commande')
                ->setHelp('Sélectionnez une commande pour lier cette facture à une commande existante.')
                ->setRequired(false)
                ->setFormTypeOption('placeholder', 'Aucune commande sélectionnée');
        }

        yield FormField::addPanel('Détails de la Facture');

        if (Crud::PAGE_EDIT === $pageName) {
            yield AssociationField::new('commande', 'Commande liée')
                ->setFormTypeOption('disabled', false)
                ->setHelp('La commande liée ne peut pas être modifiée une fois la facture créée.');
        }

        // Client
        yield AssociationField::new('client', 'Client');

        // Produits de la facture
        yield CollectionField::new('lignes', 'Produits Commandes / Services')
            ->setEntryType(FactureLigneType::class)
            ->setEntryIsComplex(true)
            ->allowAdd(true)
            ->allowDelete(true)
            ->setFormTypeOptions(['by_reference' => false]);

        // Frais de livraison
        yield MoneyField::new('fraisLivraison', 'Frais de livraison')
            ->setCurrency('MGA')
            ->setNumDecimals(0)
            ->setFormTypeOption('divisor', 1)
            ->setFormTypeOption('attr', ['class' => 'facture-frais-livraison']);

        yield TextField::new('livreur', 'Nom du livreur');

        // Acompte et remise
        yield MoneyField::new('acompte', 'Acompte')
            ->setCurrency('MGA')->setNumDecimals(0)->setFormTypeOption('divisor', 1)
            ->setFormTypeOption('attr', ['class' => 'acompte']);

        yield NumberField::new('remise', 'Remise')
            ->setFormTypeOption('attr', ['class' => 'remise']);

        yield FormField::addPanel('Conditions de Paiement');

        yield ChoiceField::new('modeDePaiement', 'Mode de paiement')
            ->setChoices([
                'Chèque' => 'Chèque',
                'Espèce' => 'Espèce',
                'Virement bancaire' => 'Virement bancaire',
                'Carte bancaire' => 'Carte bancaire',
                'Mobile Money' => 'Mobile Money'
            ])
            ->setHelp('Choisissez le moyen de paiement principal.');

        yield TextField::new('detailsPaiement', 'Détails / Références')
            ->setHelp('Ex: MVola, numéro de chèque, référence de virement...');

        yield ChoiceField::new('methodePaiement', 'Méthode de paiement')
            ->setChoices([
                '50% à la commande, 50% à la livraison' => '50% commande, 50% livraison',
                '100% à la livraison' => '100% livraison',
                '30 jours après réception de la facture' => '30 jours fin de mois',
                '100% à la commande' => '100% commande'
            ])
            ->setHelp('Choisissez les conditions de règlement.');

        // Total
        yield MoneyField::new('total', 'Total')
            ->setCurrency('MGA')
            ->setNumDecimals(0)
            ->setFormTypeOption('divisor', 1)
            ->setFormTypeOption('disabled', 'disabled')
            ->setFormTypeOption('attr', ['class' => 'facture-total-general']);

        yield DateTimeField::new('dateCreation', 'Date de création')->hideOnForm();

        // === Script dynamique ===
        yield FormField::addPanel('')
            ->setHelp(<<<HTML
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const updateFactureTotal = () => {
                            let total = 0;
                            document.querySelectorAll('[id$=_prixTotal]').forEach(input => {
                                const val = parseFloat(input.value || 0);
                                if (!isNaN(val)) total += val;
                            });
                            const fraisLivraisonInput = document.querySelector('.facture-frais-livraison');
                            if (fraisLivraisonInput) {
                                const frais = parseFloat(fraisLivraisonInput.value || 0);
                                if (!isNaN(frais)) total += frais;
                            }
                            const totalGeneralInput = document.querySelector('.facture-total-general');
                            if (totalGeneralInput) totalGeneralInput.value = total.toFixed(0);
                        };

                        const updateLine = (container) => {
                            const select = container.querySelector('select');
                            const qtyInput = container.querySelector('[id$=_quantite]');
                            const unitInput = container.querySelector('[id$=_prixUnitaire]');
                            const totalInput = container.querySelector('[id$=_prixTotal]');
                            if (!select || !qtyInput || !unitInput || !totalInput) return;
                            const selectedOption = select.options[select.selectedIndex];
                            const prix = parseFloat(selectedOption.getAttribute('data-prix') || 0);
                            const quantite = parseInt(qtyInput.value || 0);
                            if (!isNaN(prix)) unitInput.value = prix.toFixed(0);
                            if (!isNaN(prix) && !isNaN(quantite)) totalInput.value = (prix * quantite).toFixed(0);
                            updateFactureTotal();
                        };

                        const attachListeners = (container) => {
                            const select = container.querySelector('select');
                            const qtyInput = container.querySelector('[id$=_quantite]');
                            if (select) select.addEventListener('change', () => updateLine(container));
                            if (qtyInput) qtyInput.addEventListener('input', () => updateLine(container));
                        };

                        document.querySelectorAll('.form-widget-compound').forEach(attachListeners);
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

                        const fraisLivraisonInput = document.querySelector('.facture-frais-livraison');
                        if (fraisLivraisonInput) fraisLivraisonInput.addEventListener('input', updateFactureTotal);
                    });
                </script>
            HTML
            )->onlyOnForms();
    }

    public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $formBuilder = parent::createNewFormBuilder($entityDto, $formOptions, $context);
        return $this->addCommandeDataListener($formBuilder);
    }

    private function addCommandeDataListener(FormBuilderInterface $formBuilder): FormBuilderInterface
    {
        $formBuilder->addEventListener(FormEvents::PRE_SUBMIT, function(FormEvent $event) {
            $data = $event->getData();
            /** @var Facture $facture */
            $facture = $event->getForm()->getData();

            $commandeId = $data['commande'] ?? null;
            if (!$commandeId) {
                return; // Ne rien faire si aucune commande n'est sélectionnée
            }
            
            $em = $this->container->get('doctrine')->getManager();
            /** @var Commande|null $commande */
            $commande = $em->getRepository(Commande::class)->find($commandeId);
            
            if (!$commande) {
                return;
            }

            // Lier la commande à l'entité facture, qui sera persistée
            $facture->setCommande($commande);

            // Pré-remplir les données qui seront utilisées par le formulaire
            $data['client'] = $commande->getClient() ? $commande->getClient()->getId() : null;
            $data['numeroBonCommande'] = $commande->getNumeroBonCommande();

            // Vider les lignes pour les remplacer par celles de la commande
            $data['lignes'] = [];
            foreach ($commande->getCommandeProduits() as $index => $commandeLigne) {
                if ($commandeLigne->getProduit()) { // S'assurer que le produit existe
                    $data['lignes'][(string)$index] = [
                        'produit' => $commandeLigne->getProduit()->getId(),
                        'quantite' => $commandeLigne->getQuantite(),
                        // Les prix (unitaire et total) seront calculés par la méthode recalculateTotals
                    ];
                }
            }

            // Mettre à jour les données de l'événement
            $event->setData($data);
        });
        
        return $formBuilder;
    }

    private function recalculateTotals(Facture $facture): void
    {
        // On commence le total avec les frais de livraison (ou 0 si null)
        $total = $facture->getFraisLivraison() ?? 0;

        foreach ($facture->getLignes() as $ligne) {
            $produit = $ligne->getProduit();
            // S'assurer que le produit et le prix existent
            if ($produit && $produit->getPrix() !== null) {
                $ligne->setPrixUnitaire($produit->getPrix());
                $prixTotalLigne = $produit->getPrix() * $ligne->getQuantite();
                $ligne->setPrixTotal($prixTotalLigne);
                
                // S'assurer que la ligne est bien liée à la facture
                if ($ligne->getFacture() !== $facture) {
                    $ligne->setFacture($facture);
                }
                
                $total += $prixTotalLigne;
            }
        }

        $facture->setTotal($total);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Facture) return;
        
        // On appelle notre nouvelle méthode de calcul
        $this->recalculateTotals($entityInstance);

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Facture) return;

        // On appelle la même méthode ici aussi
        $this->recalculateTotals($entityInstance);

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function configureActions(Actions $actions): Actions
    {
        $exportPdf = Action::new('exportPdf', 'Exporter PDF', 'fa fa-file-pdf')
            ->linkToCrudAction('exportPdfAction')
            ->setCssClass('btn btn-primary')
            ->setHtmlAttributes([
                'target' => '_blank',
            ]);

        return $actions
            // ETAPE 1 : On AJOUTE l'action DETAIL standard à la page d'index.
            // On ne la configure pas ici, on dit juste à EasyAdmin de l'activer.
            ->add(Crud::PAGE_INDEX, Action::DETAIL)

            // ETAPE 2 : MAINTENANT qu'elle existe sur la page index, on la MET À JOUR.
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
                return $action
                    ->setLabel('Consulter');   // On change le libellé
            })
            
            // On ajoute nos autres actions personnalisées comme avant
            ->add(Crud::PAGE_INDEX, $exportPdf)
            ->add(Crud::PAGE_DETAIL, $exportPdf)

            ->reorder(Crud::PAGE_INDEX, ['exportPdf', Action::DETAIL, Action::EDIT]);
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

        $logoPath = $this->getParameter('kernel.project_dir') . '/public/utils/logo/forever.jpeg';
        $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));

        $html = $this->renderView('facture/pdf.html.twig', [
            'facture' => $facture,
            'logo' => $logoBase64,
        ]);

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
