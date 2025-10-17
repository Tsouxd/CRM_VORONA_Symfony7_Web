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
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

class DevisCrudController extends AbstractCrudController
{
    public function __construct(
        private RequestStack $requestStack,
    ) {}

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

        yield AssociationField::new('pao', 'PAO en charge')
            ->setQueryBuilder(function (QueryBuilder $qb) {
                $alias = $qb->getRootAliases()[0];
                return $qb
                    ->andWhere(sprintf('%s.roles LIKE :role', $alias))
                    ->setParameter('role', '%"ROLE_PAO"%')
                    // === LA MÊME CORRECTION ICI ===
                    ->orderBy(sprintf('%s.username', $alias), 'ASC');
            });

        yield ChoiceField::new('modeDePaiement', 'Mode de paiement')
            ->setChoices([
                'Chèque' => 'Chèque',
                'Espèce' => 'Espèce',
                'Virement bancaire' => 'Virement bancaire',
                'Carte bancaire' => 'Carte bancaire',
                'Mobile Money' => 'Mobile Money',
            ])
            ->setHelp('Choisissez le moyen de paiement principal.');

        yield MoneyField::new('remise', 'Remise')
            ->setCurrency('MGA')
            ->setNumDecimals(0)
            ->setFormTypeOption('divisor', 1)
            ->setFormTypeOption('attr', ['class' => 'remise']);

        yield TextField::new('detailsPaiement', 'Détails / Références')
            ->setHelp('Ex: MVola, numéro de chèque, référence de virement...');

        yield ChoiceField::new('methodePaiement', 'Méthode de paiement')
            ->setChoices([
                '50% à la commande, 50% à la livraison' => '50% commande, 50% livraison',
                '100% à la livraison' => '100% livraison',
                '30 jours après réception de la facture' => '30 jours fin de mois',
                '100% à la commande' => '100% commande', // Optionnel, mais souvent utile
            ])
            ->setHelp('Choisissez les conditions de règlement.');

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
        
        yield FormField::addPanel('Totalisation');

        yield MoneyField::new('acompte', 'Acompte Versé')
            ->setCurrency('MGA')
            ->setNumDecimals(0)
            ->setFormTypeOption('divisor', 1)
            ->setHelp('Montant de l\'acompte déjà payé par le client.');

        // === LE NOUVEAU CHAMP RESTE À PAYER ===
        yield MoneyField::new('resteAPayer', 'Reste à Payer')
            ->setCurrency('MGA')
            ->setNumDecimals(0)
            ->setFormTypeOption('divisor', 1)
            ->setFormTypeOption('attr', ['readonly' => true]) // Non modifiable
            ->setFormTypeOption('mapped', false)
            ->hideOnIndex(); // Optionnel : ne pas l'afficher dans la liste

        // --- Total (calculé par JS) ---
        yield MoneyField::new('total', 'Total')
            ->setCurrency('MGA')
            ->setNumDecimals(0)
            ->setFormTypeOption('divisor', 1)
            ->setFormTypeOption('attr', ['readonly' => true]);

        yield DateTimeField::new('dateCreation', 'Date de création')->hideOnForm();
        yield DateTimeField::new('dateExpiration', 'Date d’expiration')
            ->hideOnForm()
            ->setHelp('Offre valable 7 jours après création.');

        yield Field::new('offreValide', 'Validité de l’offre')
            ->onlyOnIndex()
            ->formatValue(function ($value, $entity) {
                $now = new \DateTimeImmutable();
                if (!$entity->getDateExpiration()) {
                    return ['status' => 'none', 'label' => 'Non définie'];
                }

                if ($entity->getDateExpiration() > $now) {
                    $joursRestants = $entity->getDateExpiration()->diff($now)->days;
                    return ['status' => 'valid', 'label' => "Valide ({$joursRestants} jrs restants)"];
                } else {
                    return ['status' => 'expired', 'label' => 'Expirée'];
                }
            })
            ->setTemplatePath('admin/fields/offre_valide.html.twig'); // ✅ custom template


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

        // --- Script JavaScript pour les calculs automatiques ET l'auto-soumission ---
        yield FormField::addPanel('')->setHelp(<<<HTML
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // --- 1. On sélectionne tous les éléments du formulaire dont on a besoin ---
                const linesContainer = document.querySelector('#Devis_lignes');
                const acompteInput = document.querySelector('#Devis_acompte');
                const grandTotalInput = document.querySelector('#Devis_total');
                const resteAPayerInput = document.querySelector('#Devis_resteAPayer');

                // Sécurité : si un des éléments n'est pas trouvé, on arrête pour éviter des erreurs.
                if (!linesContainer || !acompteInput || !grandTotalInput || !resteAPayerInput) {
                    console.error('Un ou plusieurs champs (lignes, total, acompte, resteAPayer) sont manquants dans le DOM.');
                    return;
                }

                // --- 2. On définit nos fonctions de calcul ---

                // Fonction A : Calcule le "Reste à payer" (Total - Acompte)
                function updateFinalTotals() {
                    const grandTotal = parseFloat(grandTotalInput.value) || 0;
                    const acompte = parseFloat(acompteInput.value) || 0;
                    resteAPayerInput.value = (grandTotal - acompte).toFixed(0);
                }

                // Fonction B : Calcule le "Total" en additionnant toutes les lignes
                function updateGrandTotal() {
                    let currentGrandTotal = 0;
                    linesContainer.querySelectorAll('.devis-prix-total').forEach(lineTotalInput => {
                        currentGrandTotal += parseFloat(lineTotalInput.value) || 0;
                    });
                    grandTotalInput.value = currentGrandTotal.toFixed(0);
                    
                    // TRES IMPORTANT : une fois le total calculé, on met à jour le reste à payer.
                    updateFinalTotals();
                }

                // Fonction C : Calcule le total d'UNE seule ligne (Quantité * Prix Unitaire)
                function updateLine(lineElement) {
                    const quantiteInput = lineElement.querySelector('.devis-quantite');
                    const prixUnitaireInput = lineElement.querySelector('.devis-prix-unitaire');
                    const prixTotalInput = lineElement.querySelector('.devis-prix-total');

                    const quantite = parseInt(quantiteInput.value) || 0;
                    const prixUnitaire = parseFloat(prixUnitaireInput.value) || 0;

                    prixTotalInput.value = (quantite * prixUnitaire).toFixed(0);
                    
                    // Chaque fois qu'une ligne change, on doit recalculer le grand total.
                    updateGrandTotal();
                }

                // Fonction D : Attache les écouteurs d'événements à une ligne de devis
                function attachLineListeners(lineElement) {
                    lineElement.querySelector('.devis-quantite').addEventListener('input', () => updateLine(lineElement));
                    lineElement.querySelector('.devis-prix-unitaire').addEventListener('input', () => updateLine(lineElement));
                }

                // --- 3. On connecte nos fonctions aux événements du formulaire ---

                // Pour les lignes déjà présentes au chargement de la page
                linesContainer.querySelectorAll('.form-widget-compound').forEach(attachLineListeners);
                
                // Pour les nouvelles lignes ajoutées via le bouton "Ajouter"
                linesContainer.addEventListener('ea.collection.item-added', (e) => attachLineListeners(e.detail.item));
                
                // Pour les lignes supprimées, on recalcule le total
                linesContainer.addEventListener('ea.collection.item-removed', updateGrandTotal);
                
                // Pour le champ "Acompte"
                acompteInput.addEventListener('input', updateFinalTotals);

                // Pour l'auto-soumission de la case "BAT OK ?"
                document.querySelectorAll('.autosubmit-checkbox input[type=checkbox]').forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        this.closest('form').submit();
                    });
                });

                // --- 4. On lance un calcul initial pour remplir les champs au chargement ---
                updateGrandTotal();

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

        // 🧮 Recalcul des totaux
        $this->recalculateTotals($entityInstance);

        // 🕒 Définir la date de création si non définie
        if ($entityInstance->getDateCreation() === null) {
            $entityInstance->setDateCreation(new \DateTimeImmutable());
        }

        // 🕓 Définir la date d’expiration automatiquement si absente
        if ($entityInstance->getDateExpiration() === null) {
            $entityInstance->setDateExpiration(
                (clone $entityInstance->getDateCreation())->modify('+8 days')
            );
        }

        // 🧾 Vérifie si le BAT est validé → changement automatique du statut
        if (method_exists($entityInstance, 'isBatOk') && $entityInstance->isBatOk()) {
            $entityInstance->setStatut('BAT/Production');
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Devis) return;

        $now = new \DateTimeImmutable();
        $dateExpiration = $entityInstance->getDateExpiration();

        // 🚫 Empêcher la modification d'un devis expiré
        if ($dateExpiration !== null && $dateExpiration < $now) {
            $this->requestStack->getSession()->getFlashBag()->add(
                'danger',
                '❌ Ce devis est expiré et ne peut plus être modifié.'
            );
            $referer = $this->requestStack->getCurrentRequest()->headers->get('referer');
            $response = new RedirectResponse($referer);
            $response->send();
            return;
        }

        // 🧮 Recalcul des totaux côté serveur
        $this->recalculateTotals($entityInstance);

        // 🕓 Mise à jour automatique de la date d’expiration si la date de création change
        if ($entityInstance->getDateCreation()) {
            $newExpiration = (clone $entityInstance->getDateCreation())->modify('+8 days');

            if ($dateExpiration === null || $dateExpiration->format('Y-m-d') !== $newExpiration->format('Y-m-d')) {
                $entityInstance->setDateExpiration($newExpiration);
            }
        }

        // 🧾 Vérifie si le BAT est validé → changement automatique du statut
        if (method_exists($entityInstance, 'isBatOk') && $entityInstance->isBatOk()) {
            $entityInstance->setStatut('BAT/Production');
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

        $logoPath = $this->getParameter('kernel.project_dir') . '/public/utils/logo/forever.jpeg';
        $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));


        $html = $this->renderView('devis/pdf.html.twig', [
            'devis' => $devis,
            'logo' => $logoBase64
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
