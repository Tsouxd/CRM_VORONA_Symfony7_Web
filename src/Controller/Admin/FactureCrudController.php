<?php
namespace App\Controller\Admin;

use App\Entity\Facture;
use App\Form\FactureLigneType;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\Commande;
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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;

class FactureCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Facture::class;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        // 1. On récupère le QueryBuilder par défaut d'EasyAdmin
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        // 2. On récupère l'utilisateur connecté
        $user = $this->getUser();

        // 3. Si l'utilisateur a le rôle COMMERCIAL mais PAS le rôle ADMIN, on filtre
        if ($this->isGranted('ROLE_COMMERCIAL') && !$this->isGranted('ROLE_ADMIN')) {
            // On récupère l'alias de la requête (généralement 'entity')
            $rootAlias = $queryBuilder->getRootAliases()[0];

            // On ajoute la condition pour ne sélectionner que les factures
            // où le champ 'commercial' correspond à l'ID de l'utilisateur connecté.
            $queryBuilder
                ->andWhere(sprintf('%s.commercial = :user', $rootAlias))
                ->setParameter('user', $user);
        }

        // 4. On retourne le QueryBuilder, qui est maintenant filtré si nécessaire
        return $queryBuilder;
    }

    public function configureFields(string $pageName): iterable
    {
        // CHAMP COMMANDE — création de facture à partir d'une commande
        if (Crud::PAGE_NEW === $pageName) {
            yield FormField::addPanel('Source de la Facture')->collapsible();

            yield AssociationField::new('commande', 'Créer à partir d\'une Commande')
                ->setHelp('Sélectionnez une commande pour lier cette facture à une commande existante.')
                ->setRequired(false)
                ->setFormTypeOption('placeholder', 'Aucune commande sélectionnée');
        }

        yield AssociationField::new('commande', 'Lier une Commande')
            ->setHelp('Sélectionnez une commande pour lier cette facture à une commande existante.')
            ->setRequired(false)
            ->onlyOnIndex()
            ->setFormTypeOption('placeholder', 'Aucune commande sélectionnée');

        // 1. On crée le champ 'commercial'
        yield AssociationField::new('commercial', 'Commercial en charge')
            // 2. On ne l'affiche que si l'utilisateur est ADMIN
            ->setPermission('ROLE_ADMIN')
            // 3. On filtre la liste pour n'afficher que les utilisateurs ayant le rôle COMMERCIAL
            ->setQueryBuilder(function (QueryBuilder $qb) {
                $alias = $qb->getRootAliases()[0]; // Récupère l'alias, ex: 'User'
                return $qb
                    ->andWhere(sprintf('%s.roles LIKE :role', $alias))
                    ->setParameter('role', '%"ROLE_COMMERCIAL"%')
                    ->orderBy(sprintf('%s.username', $alias), 'ASC');
            });

        if (Crud::PAGE_EDIT === $pageName) {
            yield AssociationField::new('commande', 'Commande liée')
                ->setFormTypeOption('disabled', false)
                ->setHelp('La commande liée ne peut pas être modifiée une fois la facture créée.');
        }

        // Client
        yield AssociationField::new('client', 'Client')->setFormTypeOption('placeholder', 'Aucun client sélectionné');

        // Produits de la facture
        yield CollectionField::new('lignes', 'Produits Commandés')
            ->setCssClass('field-from-facture')
            ->setEntryType(FactureLigneType::class)
            ->setEntryIsComplex(true)
            ->allowAdd(true)
            ->allowDelete(true)
            ->setHelp('Une fois un commande liée, les produits et quantités seront pré-remplis depuis cette commande. Donc, plus besoin de les ajouter manuellement ici.')
            ->setFormTypeOptions(['by_reference' => false]);

        // Frais de livraison
        yield MoneyField::new('fraisLivraison', 'Frais de livraison')
            ->setCurrency('MGA')
            ->setNumDecimals(0)
            ->setFormTypeOption('divisor', 1)
            ->setFormTypeOption('attr', ['class' => 'facture-frais-livraison']);

        if ($pageName === Crud::PAGE_NEW || $pageName === Crud::PAGE_EDIT) {
            yield TextField::new('livreur', 'Nom du livreur')
                //->setFormTypeOption('disabled', true)
                ->setHelp('Ce champ sera rempli automatiquement depuis la commande liée.');
        }
        yield TextField::new('livreur', 'Nom du livreur')->hideOnForm();

        yield FormField::addPanel('')
            ->setHelp(<<<'HTML'
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const commandeSelect = document.querySelector('#Facture_commande');
                const livreurField = document.querySelector('#Facture_livreur')?.closest('.form-group');

                function toggleLivreur() {
                    if (!commandeSelect || !livreurField) return;
                    livreurField.style.display = commandeSelect.value ? 'none' : 'block';
                }

                if (commandeSelect) {
                    commandeSelect.addEventListener('change', toggleLivreur);
                    toggleLivreur(); // état initial
                }
            });
            </script>
            HTML
            )->onlyOnForms()->setCssClass('d-none'); // le panneau est invisible, ne sert que pour le script

        // Acompte et remise
        yield MoneyField::new('acompte', 'Acompte')
            ->setCurrency('MGA')->setNumDecimals(0)->setFormTypeOption('divisor', 1)
            ->setFormTypeOption('attr', ['class' => 'acompte']);

        yield NumberField::new('remise', 'Remise')
            ->setFormTypeOption('attr', ['class' => 'remise']);

        yield ChoiceField::new('modeDePaiement', 'Mode de paiement')
            ->setChoices([
                'Chèque' => 'Chèque',
                'Espèce' => 'Espèce',
                'Virement Bancaire' => 'Virement Bancaire',
                'Carte bancaire' => 'Carte bancaire',
                'Mobile Money' => 'Mobile Money'
            ])
            //->setFormTypeOption('data', 'Espèce')
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

        yield FormField::addPanel('')->setHelp(<<<'HTML'
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // On s'assure que le script s'exécute aussi après les rechargements de Turbo
            document.addEventListener('turbo:load', setupCommandeToggle);
            
            function setupCommandeToggle() {
                // MODIFIÉ : On cible le sélecteur de COMMANDE
                const commandeSelect = document.querySelector('#Facture_commande');
                
                if (!commandeSelect) {
                    return; // Pas sur la bonne page ou champ introuvable
                }

                // La fonction qui fait tout le travail
                function toggleAutoFilledFields() {
                    // On vérifie si une commande est sélectionnée
                    const isCommandeLinked = !!commandeSelect.value;
                    
                    // On récupère tous les champs qu'on a marqués
                    const fieldsToToggle = document.querySelectorAll('.field-from-facture');

                    fieldsToToggle.forEach(field => {
                        // EasyAdmin enveloppe chaque champ dans un conteneur .form-group
                        const wrapper = field.closest('.form-group');
                        if (wrapper) {
                            wrapper.style.display = isCommandeLinked ? 'none' : 'block';
                        }
                    });
                }

                // On écoute les changements sur le sélecteur de commande
                commandeSelect.addEventListener('change', toggleAutoFilledFields);

                // On exécute la fonction une fois au chargement pour définir l'état initial
                toggleAutoFilledFields();
            }

            // Premier appel
            setupCommandeToggle();
        });
        </script>
        HTML)->setCssClass('d-none'); // Cache le panneau qui ne sert qu'à porter le script

        yield FormField::addPanel('')
            ->setHelp(<<<'HTML'
                <script>
                document.addEventListener('DOMContentLoaded', function() {

                    const updateFactureTotal = () => {
                        let total = 0;
                        document.querySelectorAll('[id$=_prixTotal]').forEach(input => {
                            const val = parseFloat(input.value || 0);
                            if (!isNaN(val)) total += val;
                        });
                        const fraisInput = document.querySelector('.facture-frais-livraison');
                        if (fraisInput) total += parseFloat(fraisInput.value || 0);
                        const totalInput = document.querySelector('.facture-total-general');
                        if (totalInput) totalInput.value = total.toFixed(0);
                    };

                    const attachListeners = (container) => {
                        const select = container.querySelector('select');
                        const qty = container.querySelector('[id$=_quantite]');
                        const unit = container.querySelector('[id$=_prixUnitaire]');
                        const total = container.querySelector('[id$=_prixTotal]');
                        if (!select || !qty || !unit || !total) return;
                        const updateLine = () => {
                            const prix = parseFloat(select.options[select.selectedIndex]?.getAttribute('data-prix') || 0);
                            const quantite = parseInt(qty.value || 0);
                            if (!isNaN(prix)) unit.value = prix.toFixed(0);
                            if (!isNaN(prix) && !isNaN(quantite)) total.value = (prix * quantite).toFixed(0);
                            updateFactureTotal();
                        };
                        select.addEventListener('change', updateLine);
                        qty.addEventListener('input', updateLine);
                    };

                    const toggleAutoFilledFields = (shouldHide) => {
                        // Liste des sélecteurs CSS pour les champs à masquer/afficher
                        const fieldSelectors = [
                            'select[name$="[client]"]',
                            'div[id="Facture_lignes"]',
                            'input[name$="[fraisLivraison]"]',
                            //'input[name$="[acompte]"]',
                            'select[name$="[modeDePaiement]"]',
                            'input[name$="[detailsPaiement]"]',
                            'input[name$="[total]"]',
                            'select[name$="[methodePaiement]"]'
                        ];

                        fieldSelectors.forEach(selector => {
                            const field = document.querySelector(selector);
                            if (field) {
                                // On cible le conteneur parent '.form-group' pour masquer le label avec le champ
                                const wrapper = field.closest('.form-group'); 
                                if (wrapper) {
                                    wrapper.style.display = shouldHide ? 'none' : 'block';
                                }
                            }
                        });
                    };

                    document.querySelectorAll('.form-widget-compound').forEach(attachListeners);

                    const addButton = document.querySelector('.field-collection-add-button');
                    if (addButton) {
                        addButton.addEventListener('click', () => {
                            setTimeout(() => {
                                document.querySelectorAll('.form-widget-compound:not(.listening)').forEach(container => {
                                    container.classList.add('listening');
                                    attachListeners(container);
                                });
                            }, 200);
                        });
                    }

                    const commandeSelect = document.querySelector('select[name$="[commande]"]');
                    if (!commandeSelect) return;

                    // Appel initial pour définir l'état des champs au chargement de la page
                    toggleAutoFilledFields(!!commandeSelect.value);

                    commandeSelect.addEventListener('change', function() {
                        const commandeId = this.value;

                        // On masque ou affiche les champs instantanément en fonction de la sélection
                        toggleAutoFilledFields(!!commandeId);

                        if (!commandeId) {
                            // Si aucune commande n'est sélectionnée, on arrête ici.
                            // Les champs sont déjà ré-affichés par la ligne du dessus.
                            return;
                        }

                        fetch('/api/facture/commande/' + commandeId)
                            .then(res => res.json())
                            .then(data => {

                                // Client
                                const clientSelect = document.querySelector('select[name$="[client]"]');
                                if (clientSelect && data.client) clientSelect.value = data.client;

                                // Frais livraison
                                const fraisInput = document.querySelector('.facture-frais-livraison');
                                if (fraisInput && data.fraisLivraison) fraisInput.value = data.fraisLivraison;

                                // Lignes de produits
                                const lignesContainer = document.querySelector('#Facture_lignes');
                                if (lignesContainer) {
                                lignesContainer.querySelectorAll('[data-form-collection-item-id]').forEach(el => el.remove());
                                }

                                data.lignes.forEach((ligne, index) => {
                                    const addBtn = document.querySelector('.field-collection-add-button[data-collection-id="Facture_lignes"]');
                                    if (addBtn) addBtn.click();
                                    setTimeout(() => {
                                        const allLignes = document.querySelectorAll('#Facture_lignes [data-form-collection-item-id]');
                                        const container = allLignes[allLignes.length - 1];
                                        if (!container) return;
                                        const selectProduit = container.querySelector('select[id$="_produit"]');
                                        const qtyInput = container.querySelector('input[id$="_quantite"]');
                                        const unitInput = container.querySelector('input[id$="_prixUnitaire"]');
                                        const totalInput = container.querySelector('input[id$="_prixTotal"]');
                                        if (selectProduit) selectProduit.value = ligne.produit;
                                        if (qtyInput) qtyInput.value = ligne.quantite;
                                        if (unitInput) unitInput.value = ligne.prixUnitaire;
                                        if (totalInput) totalInput.value = ligne.prixTotal;
                                        qtyInput.dispatchEvent(new Event('input'));
                                    }, 200);
                                });
                                
                                // Paiements
                                if (data.paiements && data.paiements.length > 0) {
                                    const premierPaiement = data.paiements[0];
                                    const acompteInput = document.querySelector('input[name$="[acompte]"]');
                                    if (acompteInput && premierPaiement.montant) {
                                        acompteInput.value = premierPaiement.montant;
                                    }
                                    const modePaiementSelect = document.querySelector('select[name$="[modeDePaiement]"]');
                                    if (modePaiementSelect && premierPaiement.modeDePaiement) {
                                        modePaiementSelect.value = premierPaiement.modeDePaiement;
                                    }
                                    const detailsPaiementInput = document.querySelector('input[name$="[detailsPaiement]"]');
                                    if (detailsPaiementInput) {
                                        detailsPaiementInput.value = premierPaiement.detailsPaiement ?? '';
                                    }
                                }

                                setTimeout(updateFactureTotal, 300);
                            });
                    });

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
                return;
            }
            
            $em = $this->container->get('doctrine')->getManager();
            /** @var Commande|null $commande */
            $commande = $em->getRepository(Commande::class)->find($commandeId);
            
            if (!$commande) {
                return;
            }

            // --- VOTRE LOGIQUE EXISTANTE (PARFAITE) ---
            $facture->setCommande($commande);
            $data['client'] = $commande->getClient() ? $commande->getClient()->getId() : null;
            $data['lignes'] = [];
            foreach ($commande->getCommandeProduits() as $index => $commandeLigne) {
                if ($commandeLigne->getProduit()) {
                    $data['lignes'][(string)$index] = [
                        'produit' => $commandeLigne->getProduit()->getId(),
                        'quantite' => $commandeLigne->getQuantite(),
                    ];
                }
            }
            
            // 1. On copie les CONDITIONS de règlement (ex: "50/50")
            if ($commande->getMethodePaiement()) {
                $data['methodePaiement'] = $commande->getMethodePaiement();
            }

            // 2. On récupère le PREMIER paiement de la commande pour copier les détails
            $paiements = $commande->getPaiements();
            if (!$paiements->isEmpty()) {
                $premierPaiement = $paiements->first();
                
                // On copie le MONTANT de l'acompte (si effectué)
                if ($premierPaiement->getMontant() && $premierPaiement->getStatut() === 'effectué') {
                    $data['acompte'] = $premierPaiement->getMontant();
                } else {
                    $data['acompte'] = 0;
                }
                
                // On copie le MOYEN de paiement (Espèces, Chèque...)
                // C'est la referencePaiement de l'entité Paiement
                if ($premierPaiement->getReferencePaiement()) { 
                    $data['modeDePaiement'] = $premierPaiement->getReferencePaiement();
                }

                // On copie les DÉTAILS du paiement
                if ($premierPaiement->getDetailsPaiement()) {
                    $data['detailsPaiement'] = $premierPaiement->getDetailsPaiement();
                }
            }

            // Mettre à jour les données de l'événement (l'étape cruciale)
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

        // Lier le commercial si vide
        if (null === $entityInstance->getCommercial()) {
            $entityInstance->setCommercial($this->getUser());
        }

        // Copier le nom du livreur depuis la commande si existante
        if ($entityInstance->getCommande() && $entityInstance->getCommande()->getNomLivreur()) {
            $entityInstance->setLivreur($entityInstance->getCommande()->getNomLivreur());
        }

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
            //->setCssClass('btn btn-secondary') // J'ai mis secondary pour différencier les boutons
            ->setHtmlAttributes([
                'target' => '_blank',
            ]);

        $exportTicket = Action::new('exportTicket', 'Ticket de caisse', 'fa fa-receipt')
            ->linkToCrudAction('exportTicketAction')
            //->setCssClass('btn btn-light')
            ->setHtmlAttributes(['target' => '_blank']);

        /*$exportFactureProforma = Action::new('exportFactureProforma', 'Facture Proforma', 'fa fa-file-invoice-dollar')
            ->linkToCrudAction('exportFactureProformaAction')
            //->setCssClass('btn btn-light')
            ->setHtmlAttributes(['target' => '_blank']);*/

        return $actions
            // ETAPE 1 : On AJOUTE l'action DETAIL standard à la page d'index.
            ->add(Crud::PAGE_INDEX, Action::DETAIL)

            // ETAPE 2 : On MET À JOUR l'action DETAIL.
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
                return $action
                    ->setLabel('Consulter');
            })
            
            // On ajoute nos autres actions personnalisées
            ->add(Crud::PAGE_INDEX, $exportPdf)
            ->add(Crud::PAGE_DETAIL, $exportPdf)
            ->add(Crud::PAGE_INDEX, $exportTicket)
            ->add(Crud::PAGE_DETAIL, $exportTicket)
            /*->add(Crud::PAGE_INDEX, $exportFactureProforma)
            ->add(Crud::PAGE_DETAIL, $exportFactureProforma)*/

            // On utilise les noms des actions (chaînes de caractères)
            ->reorder(Crud::PAGE_INDEX, ['exportTicket', 'exportPdf', /*'exportFactureProforma',*/ Action::DETAIL, Action::EDIT])
            ->reorder(Crud::PAGE_DETAIL, ['exportTicket', 'exportPdf', /*'exportFactureProforma',*/ Action::EDIT, Action::DELETE]);
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

        $logoPath = $this->getParameter('kernel.project_dir') . '/public/utils/logo/Fichier 2-8.png';
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

    /*public function exportFactureProformaAction(AdminUrlGenerator $adminUrlGenerator, EntityManagerInterface $entityManager): Response
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

        $html = $this->renderView('facture/factureProforma.html.twig', [
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
    }*/

    public function exportTicketAction(EntityManagerInterface $entityManager): Response
    {
        $id = $this->getContext()->getRequest()->query->get('entityId');
        $facture = $entityManager->getRepository(Facture::class)->find($id);

        if (!$facture) {
            throw $this->createNotFoundException("Facture non trouvée");
        }

        // --- Calculs spécifiques pour le ticket de caisse ---

        // 1. Calculer le sous-total
        $sousTotal = 0;
        foreach ($facture->getLignes() as $ligne) {
            $sousTotal += $ligne->getPrixTotal();
        }

        // On s'assure juste qu'elle n'est pas nulle pour le calcul.
        $remise = $facture->getRemise() ?? 0;
        
        // 3. Calculer le montant final restant à payer
        $resteAPayer = ($sousTotal - $remise) + $facture->getFraisLivraison() - $facture->getAcompte();

        // --- Préparation de Dompdf ---
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);

        $logoPath = $this->getParameter('kernel.project_dir') . '/public/utils/logo/forever.jpeg';
        $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));

        // On ne passe plus 'remiseEnValeur' au template.
        $html = $this->renderView('ticket_caisse/pdf.html.twig', [
            'facture' => $facture,
            'logo' => $logoBase64,
            'sousTotal' => $sousTotal,
            'resteAPayer' => $resteAPayer,
        ]);

        $dompdf->loadHtml($html);
        //$dompdf->setPaper('A4', 'portrait');
        // Largeur: 105mm (~297 points), Hauteur: 148mm (~420 points)
        $dompdf->setPaper([0, 0, 297, 420]);
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="ticket-caisse-'.$facture->getId().'.pdf"',
            ]
        );
    }

    #[Route('/api/facture/commande/{id}', name: 'api_facture_commande', methods: ['GET'])]
    public function getCommandeData(Commande $commande): JsonResponse
    {
        // --- Lignes CommandeProduits ---
        $lignes = [];
        foreach ($commande->getCommandeProduits() as $commandeLigne) {
            $lignes[] = [
                'produit' => $commandeLigne->getProduit()?->getId(),
                'quantite' => $commandeLigne->getQuantite(),
                'prixUnitaire' => $commandeLigne->getProduit()?->getPrix(),
                'prixTotal' => $commandeLigne->getQuantite() * $commandeLigne->getProduit()?->getPrix(),
            ];
        }

        // --- Paiements ---
        $paiements = [];
        foreach ($commande->getPaiements() as $paiement) {
            $paiements[] = [
                'id' => $paiement->getId(),
                'montant' => $paiement->getMontant(),
                'datePaiement' => $paiement->getDatePaiement()?->format('Y-m-d H:i:s'),
                'modeDePaiement' => $paiement->getReferencePaiement(),
                'detailsPaiement' => $paiement->getDetailsPaiement(),
            ];
        }

        return new JsonResponse([
            'id' => $commande->getId(),
            'dateCommande' => $commande->getDateCommande()?->format('Y-m-d H:i:s'),
            'isFacture' => $commande->isFacture(),
            'client' => $commande->getClient()?->getId(),
            'statut' => $commande->getStatut(),
            'fraisLivraison' => $commande->getFraisLivraison(),
            'categorie' => $commande->getCategorie(),
            'description' => $commande->getDescription(),
            'priorite' => $commande->getPriorite(),
            'statutPao' => $commande->getStatutPao(),
            'paoFichierOk' => $commande->isPaoFichierOk(),
            'paoBatOk' => $commande->isPaoBatOk(),
            'paoBatValidation' => $commande->getPaoBatValidation(),
            'paoMotifModification' => $commande->getPaoMotifModification(),
            'paoModif1Ok' => $commande->isPaoModif1Ok(),
            'paoModif2Ok' => $commande->isPaoModif2Ok(),
            'paoModif3Ok' => $commande->isPaoModif3Ok(),
            'paoMotifM1' => $commande->getPaoMotifM1(),
            'paoMotifM2' => $commande->getPaoMotifM2(),
            'paoMotifM3' => $commande->getPaoMotifM3(),
            'statutProduction' => $commande->getStatutProduction(),
            'productionTermineeOk' => $commande->isProductionTermineeOk(),
            'nomLivreur' => $commande->getNomLivreur(),
            'statutLivraison' => $commande->getStatutLivraison(),
            'dateDeLivraison' => $commande->getDateDeLivraison()?->format('Y-m-d H:i:s'),
            'statutDevis' => $commande->getStatutDevis(),
            'devisOrigine' => $commande->getDevisOrigine()?->getId(),
            'pao' => $commande->getPao()?->getId(),
            'lieuDeLivraison' => $commande->getLieuDeLivraison(),
            'commercial' => $commande->getCommercial()?->getId(),
            'production' => $commande->getProduction()?->getId(),
            'blGenere' => $commande->isBlGenere(),
            'paoStatusUpdatedAt' => $commande->getPaoStatusUpdatedAt()?->format('Y-m-d H:i:s'),
            'productionStatusUpdatedAt' => $commande->getProductionStatusUpdatedAt()?->format('Y-m-d H:i:s'),

            // Ajout du tableau des paiements
            'paiements' => $paiements,

            // Lignes facture
            'lignes' => $lignes,
        ]);
    }
}
