<?php
namespace App\Controller\Admin;

use App\Entity\Devis;
use App\Form\DevisLigneType;
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

class DevisCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Devis::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('client', 'Client');

        yield CollectionField::new('lignes', 'Produits')
            ->setEntryType(DevisLigneType::class)
            ->setEntryIsComplex(true)
            ->allowAdd(true)
            ->allowDelete(true)
            ->setFormTypeOptions([
                'by_reference' => false,
            ]);

        yield MoneyField::new('total', 'Total')
            ->setCurrency('MGA')
            ->setNumDecimals(0)
            ->setFormTypeOption('divisor', 1)
            // On retire 'disabled' pour que notre JS puisse le modifier,
            // mais on le met en readonly pour que l'utilisateur ne le change pas.
            ->setFormTypeOption('attr', ['readonly' => true]);

        yield DateTimeField::new('dateCreation', 'Date de création')->hideOnForm();

        yield FormField::addPanel('Produits du devis')->setHelp(<<<HTML
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Fonction pour mettre à jour prixUnitaire et total
                    const updateFields = (container) => {
                        const selectElement = container.querySelector('.devis-produit-select');
                        const quantiteInput = container.querySelector('.devis-quantite');
                        const prixInput = container.querySelector('.devis-prix-unitaire');
                        const totalInput = container.querySelector('.devis-total');

                        if (!selectElement || !quantiteInput || !prixInput || !totalInput) return;

                        const selectedOption = selectElement.options[selectElement.selectedIndex];
                        const prix = selectedOption ? (selectedOption.getAttribute('data-prix') || 0) : 0;
                        const quantite = quantiteInput.value || 0;

                        prixInput.value = parseInt(prix).toFixed(0);
                        totalInput.value = (prix * quantite).toFixed(0);
                    };

                    // Attacher les événements
                    const attachListeners = (container) => {
                        const select = container.querySelector('.devis-produit-select');
                        const quantite = container.querySelector('.devis-quantite');
                        if (select) select.addEventListener('change', () => updateFields(container));
                        if (quantite) quantite.addEventListener('input', () => updateFields(container));
                    };

                    // ✅ Pour les lignes déjà présentes (édition)
                    document.querySelectorAll('.form-widget-compound').forEach(container => {
                        attachListeners(container);
                        updateFields(container); // On met à jour immédiatement les champs
                    });

                    // Pour les nouvelles lignes ajoutées
                    const addButton = document.querySelector('.field-collection-add-button');
                    if (addButton) {
                        addButton.addEventListener('click', function() {
                            setTimeout(() => {
                                document.querySelectorAll('.form-widget-compound').forEach(container => {
                                    if (!container.classList.contains('listening')) {
                                        attachListeners(container);
                                        updateFields(container); // On initialise aussi les nouveaux
                                        container.classList.add('listening');
                                    }
                                });
                            }, 100);
                        });
                    }
                });
            </script>
        HTML
        )->onlyOnForms();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Devis) return;

        $total = 0;

        foreach ($entityInstance->getLignes() as $ligne) {
            $produit = $ligne->getProduit();
            if ($produit) {
                $ligne->setPrixUnitaire($produit->getPrix());
                $ligne->setPrixTotal($produit->getPrix() * $ligne->getQuantite());
                $ligne->setDevis($entityInstance); // relie la ligne au devis
                $total += $ligne->getPrixTotal();
            }
        }

        $entityInstance->setTotal($total);

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        // Même logique que persist pour recalculer les totaux
        if ($entityInstance instanceof Devis) {
            $total = 0;
            foreach ($entityInstance->getLignes() as $ligne) {
                $produit = $ligne->getProduit();
                if ($produit) {
                    $ligne->setPrixUnitaire($produit->getPrix());
                    $ligne->setPrixTotal($produit->getPrix() * $ligne->getQuantite());
                    $ligne->setDevis($entityInstance);
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
