<?php

namespace App\Controller\Admin;

// En haut du fichier (imports)
use Doctrine\ORM\PersistentCollection;
use App\Entity\Commande;
use App\Form\CommandeProduitType;
use App\Form\ClientOrNewClientType;
use App\Form\PaiementType;
use App\Form\ClientType;
use App\Entity\CommandeProduit;
use App\Entity\Paiement;
use App\Controller\Admin\PaoCrudController;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use App\Controller\Admin\ClientCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use Vich\UploaderBundle\Form\Type\VichFileType;
use Symfony\Component\HttpFoundation\Response;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;

// Import des classes nécessaires pour les filtres
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Bundle\SecurityBundle\Security;

class CommandeCrudController extends AbstractCrudController implements EventSubscriberInterface
{
    private $requestStack;
    private EntityManagerInterface $entityManager;
    private Security $security;

    public function __construct(Security $security, RequestStack $requestStack, EntityManagerInterface $entityManager)
    {
        $this->requestStack = $requestStack;
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    public static function getSubscribedEvents()
    {
        return [
            BeforeEntityUpdatedEvent::class => ['beforeUpdate'],
            BeforeEntityPersistedEvent::class => ['beforePersist'],
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        // --- Actions PDF ---
        $exportPdf = Action::new('exportPdf', 'PDF', 'fa fa-file-pdf')
            ->linkToUrl(function (Commande $commande) {
                return $this->generateUrl('admin_export_facture', ['id' => $commande->getId()]);
            })
            ->setHtmlAttributes(['target' => '_blank'])
            ->setCssClass('btn btn-secondary btn-sm');

        $actions = $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $exportPdf)
            ->add(Crud::PAGE_DETAIL, $exportPdf)
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) =>
                $action->displayIf(fn () => $this->isGranted('ROLE_ADMIN'))
            )
            ->update(Crud::PAGE_DETAIL, Action::DELETE, fn (Action $action) =>
                $action->displayIf(fn () => $this->isGranted('ROLE_ADMIN'))
            );

        // ⚡ Ici on enlève le bouton "Supprimer la sélection" pour les commerciaux
        if ($this->isGranted('ROLE_COMMERCIAL')) {
            $actions = $actions->disable(Action::BATCH_DELETE);
        }

        return $actions;
    }


    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            // Pour filtrer par la date de la commande
            ->add(DateTimeFilter::new('dateCommande', 'Date de commande'))
            
            // Pour filtrer en sélectionnant un client dans une liste
            ->add(EntityFilter::new('client', 'Client'))
            
            // Pour filtrer par le statut de la commande
            ->add(ChoiceFilter::new('statut', 'Statut')->setChoices([
                'En attente' => 'en attente',
                'Partiellement payée' => 'partiellement payée',
                'Payée' => 'payée',
                'En cours' => 'en cours',
                'Livrée' => 'livrée',
                'Annulée' => 'annulée',
            ]))

            // Pour filtrer par le statut de PAO
            ->add(ChoiceFilter::new('statutPao', 'Statut Pao')->setChoices([
                'En attente' => 'en attente',
                'Fait' => 'fait',
                'En cours' => 'en cours',
            ]));
    }
    
    public static function getEntityFqcn(): string
    {
        return Commande::class;
    }

    public function beforeUpdate(BeforeEntityUpdatedEvent $event)
    {
        $commande = $event->getEntityInstance();
        if (!$commande instanceof Commande) {
            return;
        }

        $em  = $this->entityManager;
        $uow = $em->getUnitOfWork();

        $seuilFaibleStock     = 10;
        $stockSuffisant       = true;
        $produitsInsuffisants = [];
        $produitsFaibleStock  = [];

        // 1) Lignes actuelles (ajouts + modifs de quantité + changement de produit)
        $collection = $commande->getCommandeProduits();

        foreach ($collection as $cp) {
            $produitActuel = $cp->getProduit();
            if (!$produitActuel) { continue; }

            // Anciennes valeurs de CETTE ligne (renvoie [] si c'est une nouvelle ligne)
            $oldData       = $uow->getOriginalEntityData($cp);
            $ancienneQte   = $oldData['quantite'] ?? 0;
            $ancienProduit = $oldData['produit']  ?? null;

            // Cas 1 : le produit de la ligne a changé (A -> B)
            if ($ancienProduit && $ancienProduit !== $produitActuel) {
                // on recrédite totalement l’ancien produit
                $ancienProduit->setStock($ancienProduit->getStock() + $ancienneQte);
                $em->persist($ancienProduit);

                // et on traite l’actuel comme un ajout "from scratch"
                $difference = $cp->getQuantite(); // ancienne quantité = 0 pour le nouveau produit
            } else {
                // Cas 2 : même produit, on traite la différence de quantité
                $difference = $cp->getQuantite() - $ancienneQte;
            }

            if ($difference > 0) {
                // on débite du stock
                if ($produitActuel->getStock() < $difference) {
                    $stockSuffisant = false;
                    $produitsInsuffisants[] = $produitActuel->getNom()
                        . " (Demandé: {$difference}, Disponible: " . $produitActuel->getStock() . ")";
                    // on ne modifie pas le stock si insuffisant
                } else {
                    $produitActuel->setStock($produitActuel->getStock() - $difference);
                    $em->persist($produitActuel);

                    if ($produitActuel->getStock() <= $seuilFaibleStock) {
                        $produitsFaibleStock[] = $produitActuel->getNom()
                            . " (Stock restant: " . $produitActuel->getStock() . ")";
                    }
                }
            } elseif ($difference < 0) {
                // quantité diminuée => on crédite la différence absolue
                $produitActuel->setStock($produitActuel->getStock() + abs($difference));
                $em->persist($produitActuel);
            }
        }

        // 2) Lignes supprimées (restaurer le stock)
        $suppr = [];
        if ($collection instanceof PersistentCollection) {
            // éléments retirés de la collection depuis le chargement
            $suppr = $collection->getDeleteDiff();
        } else {
            // fallback (rare) : reconstruire via snapshot si besoin
            // $snapshot = method_exists($collection, 'getSnapshot') ? $collection->getSnapshot() : [];
            // comparer $snapshot et $collection...
        }

        foreach ($suppr as $cpSupprime) {
            $produit = $cpSupprime->getProduit();
            if ($produit) {
                $produit->setStock($produit->getStock() + $cpSupprime->getQuantite());
                $em->persist($produit);
            }
        }

        // 3) Bloquer si insuffisant + warnings faible stock
        if (!$stockSuffisant) {
            $messageErreur = "Stock insuffisant pour les produits suivants : " . implode(", ", $produitsInsuffisants);
            $this->addFlash('danger', $messageErreur);
        }

        if (!empty($produitsFaibleStock)) {
            $messageAlerte = "Attention : faible stock pour les produits suivants : " . implode(", ", $produitsFaibleStock);
            $this->addFlash('warning', $messageAlerte);
        }

        $commande->updateStatutPaiement();
    }

    private function processProductChanges(Commande $commande, array $originalCommandeProduits, EntityManagerInterface $entityManager)
    {
        $seuilFaibleStock = 10;
        $stockSuffisant = true;
        $produitsInsuffisants = [];
        $produitsFaibleStock = [];
        
        $nouveauxCommandeProduits = [];
        foreach ($commande->getCommandeProduits() as $cp) {
            if ($cp->getProduit() === null) continue;
            
            $produitId = $cp->getProduit()->getId();
            $nouveauxCommandeProduits[$produitId] = $cp;
        }
        
        foreach ($nouveauxCommandeProduits as $produitId => $cp) {
            $produit = $cp->getProduit();
            $nouvelleQuantite = $cp->getQuantite();
            
            $ancienneQuantite = $originalCommandeProduits[$produitId]['quantite'] ?? 0;
            $difference = $nouvelleQuantite - $ancienneQuantite;
            
            if ($difference > 0) {
                if ($produit->getStock() < $difference) {
                    $stockSuffisant = false;
                    $produitsInsuffisants[] = $produit->getNom() . ' (Demandé en plus: ' . $difference . ', Disponible: ' . $produit->getStock() . ')';
                } else {
                    $produit->setStock($produit->getStock() - $difference);
                    if ($produit->getStock() <= $seuilFaibleStock) {
                        $produitsFaibleStock[] = $produit->getNom() . ' (Stock restant: ' . $produit->getStock() . ')';
                    }
                }
            } elseif ($difference < 0) {
                $produit->setStock($produit->getStock() + abs($difference));
            }
        }
        
        foreach ($originalCommandeProduits as $produitId => $data) {
            if (!isset($nouveauxCommandeProduits[$produitId])) {
                $produit = $data['produit'];
                $quantite = $data['quantite'];
                $produit->setStock($produit->getStock() + $quantite);
            }
        }
        
        if (!$stockSuffisant) {
            $messageErreur = 'Stock insuffisant pour les produits suivants : ' . implode(', ', $produitsInsuffisants);
            $this->addFlash('danger', $messageErreur);
            throw new \Exception($messageErreur);
        }
        
        if (!empty($produitsFaibleStock)) {
            $messageAlerte = 'Attention : Faible stock pour les produits suivants : ' . implode(', ', $produitsFaibleStock);
            $this->addFlash('warning', $messageAlerte);
        }
    }

    public function beforePersist(BeforeEntityPersistedEvent $event)
    {
        $commande = $event->getEntityInstance();
        if (!$commande instanceof Commande) return;

        $entity = $event->getEntityInstance();
        
        if (!$entity instanceof Commande) {
            return;
        }

        $entityManager = $this->container->get('doctrine')->getManager();
        $stockSuffisant = true;
        $produitsInsuffisants = [];
        $produitsFaibleStock = [];
        $seuilFaibleStock = 10;
        
        foreach ($entity->getCommandeProduits() as $cp) {
            $produit = $cp->getProduit();
            $quantite = $cp->getQuantite();
            
            if ($produit->getStock() < $quantite) {
                $stockSuffisant = false;
                $produitsInsuffisants[] = $produit->getNom() . ' (Demandé: ' . $quantite . ', Disponible: ' . $produit->getStock() . ')';
            }
        }
        
        if (!$stockSuffisant) {
            $messageErreur = 'Stock insuffisant pour les produits suivants : ' . implode(', ', $produitsInsuffisants);
            $this->addFlash('danger', $messageErreur);
            throw new \Exception($messageErreur);
        }
        
        foreach ($entity->getCommandeProduits() as $cp) {
            $produit = $cp->getProduit();
            $quantite = $cp->getQuantite();
            $nouveauStock = $produit->getStock() - $quantite;
        
            if ($nouveauStock <= $seuilFaibleStock) {
                $produitsFaibleStock[] = $produit->getNom() . ' (Stock restant: ' . $nouveauStock . ')';
            }
            $produit->setStock($nouveauStock);
        }
        
        if (!empty($produitsFaibleStock)) {
            $messageAlerte = 'Attention : Faible stock pour les produits suivants : ' . implode(', ', $produitsFaibleStock);
            $this->addFlash('warning', $messageAlerte);
        }

        $commande->updateStatutPaiement();
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Commande) {
            parent::deleteEntity($entityManager, $entityInstance);
            return;
        }
        
        foreach ($entityInstance->getCommandeProduits() as $cp) {
            $produit = $cp->getProduit();
            $quantite = $cp->getQuantite();
            $produit->setStock($produit->getStock() + $quantite);
            $entityManager->persist($produit);
        }
        $entityManager->flush();
        parent::deleteEntity($entityManager, $entityInstance);
    }
    
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield DateTimeField::new('dateCommande', 'Date de Commande')
            ->setFormat('dd/MM/yyyy HH:mm')
            //->setFormTypeOption('data', new \DateTime()) // valeur par défaut
            ->onlyOnForms();

        yield DateTimeField::new('dateCommande', 'Date de Commande')
            ->hideOnForm();

        // Affichage standard sur index/detail
        yield AssociationField::new('client')
            ->hideOnForm();

        yield AssociationField::new('pao', 'Responsable PAO')
            ->hideOnIndex();

        // SI on est sur la page de CRÉATION (new)
        if (Crud::PAGE_NEW === $pageName) {
            yield FormField::addPanel('Informations du Client')
            ->setHelp('Choisissez un client existant ou créez-en un nouveau.');

            yield Field::new('clientSelector', 'Client')
                ->setFormType(ClientOrNewClientType::class)
                ->setFormTypeOptions([
                    'label' => false,
                    'mapped' => false, 
                ]);
        }

        // SI on est sur la page de MODIFICATION (edit)
        if (Crud::PAGE_EDIT === $pageName) {
            yield FormField::addPanel('Informations du Client');
            yield AssociationField::new('client', 'Client');
                //->setFormTypeOption('disabled', true); // Grise le champ pour qu'il ne soit pas modifiable
        }

        yield MoneyField::new('totalAvecFrais', 'Total à Payer')
            ->setCurrency('MGA')
            ->setFormTypeOption('divisor', 1)
            ->setNumDecimals(0)
            ->hideOnForm();

        yield MoneyField::new('montantPaye', 'Montant Payé')
            ->setCurrency('MGA')
            ->setFormTypeOption('divisor', 1)
            ->setCssClass('text-success')
            ->setNumDecimals(0)
            ->hideOnForm();

        yield MoneyField::new('resteAPayer', 'Reste à payer')
            ->setCurrency('MGA')
            // Votre logique de formatage est toujours parfaite.
            ->formatValue(function ($value, Commande $entity) {
                $reste = $entity->getResteAPayer();

                if ($reste <= 0) {
                    return '<span class="badge bg-success">Paiement effectué</span>';
                }

                $formattedValue = number_format($reste, 0, ',', ' ') . ' MGA';
                return sprintf('<span class="font-weight-bold text-danger">%s</span>', $formattedValue);
            })
            
            // ✅ LA CORRECTION : On revient à la méthode qui fonctionne pour TOUS les champs.
            ->setCustomOption('renderAsHtml', true)
            
            ->hideOnForm();

        // --- Panneau Produits ---
        yield FormField::addPanel('Lignes de produits')->onlyOnForms();
        yield CollectionField::new('commandeProduits', 'Produits commanders')
            //->setLabel(false) // Le label est déjà dans le panneau
            ->setEntryType(CommandeProduitType::class) // C'est ici que la magie opère
            ->setFormTypeOptions(['by_reference' => false])
            ->setEntryIsComplex(true)
            ->allowAdd()
            //->hideOnIndex()
            ->allowDelete();

        /*yield CollectionField::new('commandeProduits', 'Produits')
            ->hideOnForm();*/
        
        // ✅ C'est ce champ qui remplace le PaiementCrudController
        yield CollectionField::new('paiements', 'Paiements')
            ->setEntryType(PaiementType::class)
            ->setFormTypeOptions(['by_reference' => false])
            ->allowAdd()
            ->allowDelete()
            ->hideOnIndex();

        /*yield CollectionField::new('paiements', 'Tranche de paiements')
            ->hideOnForm();*/

        // === AJOUTEZ CE BLOC COMPLET À LA FIN DE VOTRE FORMULAIRE ===
        yield FormField::addPanel('Validation PAO / Bon à Tirer (BAT)')->collapsible()
            ->setHelp('Vous disposez de 3 cycles de modification. Après la 3ème modification, la validation sera automatique.');

        // Le commercial voit le travail du PAO en lecture seule
        yield BooleanField::new('paoFichierOk', 'Fichier OK ?')->setFormTypeOption('disabled', true);
        yield BooleanField::new('paoBatOk', 'BAT Prêt ?')->setFormTypeOption('disabled', true);
        yield ChoiceField::new('paoBatValidation', 'Statut PAO')
            ->setChoices([
                'En attente de validation' => Commande::BAT_EN_ATTENTE,
                'Modification à faire' => Commande::BAT_MODIFICATION,
                'Valider pour la production' => Commande::BAT_PRODUCTION,
            ])
            ->renderAsBadges([
                Commande::BAT_EN_ATTENTE => 'secondary',
                Commande::BAT_MODIFICATION => 'danger',
                Commande::BAT_PRODUCTION => 'success',
            ]);

        // Champ de SAISIE pour la PROCHAINE modification
        yield TextareaField::new('paoMotifModification', 'Motif de la modification à faire')
            ->setHelp('À remplir OBLIGATOIREMENT si vous demandez une modification.')
            ->setCssClass('bat-motif-field');

        // Affichage de l'HISTORIQUE des motifs (lecture seule)
        yield TextareaField::new('paoMotifM1', 'Motif Modif. 1')->setFormTypeOption('disabled', true);
        yield TextareaField::new('paoMotifM2', 'Motif Modif. 2')->setFormTypeOption('disabled', true);
        yield TextareaField::new('paoMotifM3', 'Motif Modif. 3')->setFormTypeOption('disabled', true);

        // Suivi des cases cochées par le PAO
        yield BooleanField::new('paoModif1Ok', 'Modif 1 Faite')->setFormTypeOption('disabled', true);
        yield BooleanField::new('paoModif2Ok', 'Modif 2 Faite')->setFormTypeOption('disabled', true);
        yield BooleanField::new('paoModif3Ok', 'Modif 3 Faite')->setFormTypeOption('disabled', true);

        // Script pour afficher/cacher le champ motif
        yield FormField::addPanel('')->setHelp(<<<HTML
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const batValidationSelect = document.querySelector('#Commande_paoBatValidation');
                    // On cible l'élément parent pour bien cacher le label aussi
                    const motifWrapper = document.querySelector('.bat-motif-field').closest('.form-group');

                    function toggleMotifField() {
                        // On vérifie que les éléments existent avant de les manipuler
                        if (!batValidationSelect || !motifWrapper) return;
                        
                        if (batValidationSelect.value === 'Modification demandée') {
                            motifWrapper.style.display = 'block';
                        } else {
                            motifWrapper.style.display = 'none';
                        }
                    }

                    // Au chargement de la page et lors d'événements Turbo
                    document.addEventListener('turbo:load', toggleMotifField);
                    toggleMotifField();

                    // À chaque changement du select
                    if (batValidationSelect) {
                        batValidationSelect.addEventListener('change', toggleMotifField);
                    }
                });
            </script>
        HTML)->setCssClass('d-none'); // Cache le panneau

        yield FormField::addPanel('')->setHelp(<<<HTML
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Fonction pour mettre à jour le prix
                    const updatePrice = (selectElement) => {
                        const selectedOption = selectElement.options[selectElement.selectedIndex];
                        const prix = selectedOption.getAttribute('data-prix') || 0;

                        // Trouver le champ prixUnitaire qui correspond à ce select
                        const priceInput = selectElement.closest('.form-widget-compound').querySelector('[id$=_prixUnitaire]');
                        if (priceInput) {
                            priceInput.value = (prix / 1).toFixed(0); // Divisor 1, 0 décimales
                        }
                    };

                    // Attacher l'événement aux selects déjà présents sur la page
                    document.querySelectorAll('.commande-produit-select').forEach(select => {
                        // Mise à jour initiale si un produit est déjà sélectionné (édition)
                        updatePrice(select);

                        // Ajout du listener pour changements futurs
                        select.addEventListener('change', function() {
                            updatePrice(this);
                        });
                    });

                    // Gérer les nouveaux éléments ajoutés par EasyAdmin
                    const addButton = document.querySelector('.field-collection-add-button');
                    if (addButton) {
                        addButton.addEventListener('click', function() {
                            setTimeout(() => {
                                const newSelects = document.querySelectorAll('.commande-produit-select:not(.listening)');
                                newSelects.forEach(select => {
                                    select.classList.add('listening');
                                    // Mise à jour initiale du nouveau champ
                                    updatePrice(select);
                                    // Ajout du listener
                                    select.addEventListener('change', function() {
                                        updatePrice(this);
                                    });
                                });
                            }, 100);
                        });
                    }
                });
            </script>
        HTML
        )->onlyOnForms();

        // Upload (formulaire)
        yield TextField::new('pieceJointeFile')
            ->setFormType(VichFileType::class)
            ->onlyOnForms();

        // Affichage (index/detail) → lien cliquable
        yield TextField::new('pieceJointe')
            ->formatValue(function ($value, $entity) {
                if (!$value) {
                    return null;
                }
                return sprintf(
                    '<a href="/uploads/pieces/%s" target="_blank">📂 Voir le fichier</a>',
                    $value
                );
            })  
            ->hideOnForm()
            ->renderAsHtml();
            
        // Assurez-vous d'avoir les nouveaux statuts danDes le ChoiceField
        yield ChoiceField::new('statut')
            ->setChoices([
                'En attente' => 'en attente',
                'Partiellement payée' => 'partiellement payée', // AJOUTÉ
                'Payée' => 'payée',                           // AJOUTÉ
                'En cours' => 'en cours',
                'Livrée' => 'livrée',
                'Annulée' => 'annulée',
            ])
            ->renderAsBadges([
                'en attente' => 'secondary',
                'partiellement payée' => 'warning',
                'payée' => 'info',
                'en cours' => 'primary',
                'livrée' => 'success',
                'annulée' => 'danger',
            ]);

        if ($this->security->isGranted('ROLE_PAO')) {
            yield ChoiceField::new('statutPao', 'Statut PAO')
                ->setChoices([
                    'En attente' => 'en attente',
                    'En cours' => 'en cours',
                    'Fait' => 'fait',
                ])
                ->renderAsBadges([
                    'en attente' => 'secondary',
                    'en cours' => 'primary',
                    'fait' => 'success',
                ]);
        }

        yield MoneyField::new('fraisLivraison', 'Frais de livraison')
            ->setCurrency('MGA')
            ->setStoredAsCents(false)
            ->setNumDecimals(0)
            ->hideOnIndex();

        yield TextField::new('categorie', 'Catégorie')
            ->setRequired(false)
            ->hideOnIndex();

        yield TextareaField::new('description', 'Description')
            ->setRequired(false)
            ->hideOnIndex();

        yield ChoiceField::new('priorite', 'Priorité')
            ->setChoices([
                'Urgent' => 'urgent',
                'Normal' => 'normal',
                'Faible' => 'faible',
            ])
            ->renderAsBadges([
                'urgent' => 'danger',
                'normal' => 'primary',
                'faible' => 'secondary',
            ])
            ->setRequired(false);

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
    }

    public function requestModification(
        AdminContext $context,
        EntityManagerInterface $em,
        AdminUrlGenerator $adminUrlGenerator
    ): Response
    {
        /** @var Commande|null $commande */
        $commande = $context->getEntity()->getInstance();
        if (!$commande) {
            $this->addFlash('danger', 'Commande introuvable.');
            $url = $adminUrlGenerator->setController(self::class)->setAction('index')->generateUrl();
            return $this->redirect($url);
        }

        $motif = $context->getRequest()->query->get('motif', 'Aucun motif fourni.');

        $commande->setDemandeModificationStatut('requested');
        $commande->setDemandeModificationMotif($motif);
        $em->flush();

        $this->addFlash('info', 'Votre demande de modification a été envoyée à un administrateur.');

        // fallback vers l'index si pas de referrer
        $url = $context->getReferrer() ?? $adminUrlGenerator->setController(self::class)->setAction('index')->generateUrl();

        return $this->redirect($url);
    }

    public function approveRequest(AdminContext $context, EntityManagerInterface $em, AdminUrlGenerator $adminUrlGenerator): Response
    {
        $commande = $context->getEntity()->getInstance();
        $commande->setDemandeModificationStatut('approved');
        $em->flush();

        $this->addFlash('success', 'La demande de modification a été approuvée.');

        // Si pas de referrer → retour à l’index des commandes
        $url = $context->getReferrer() 
            ?? $adminUrlGenerator
                ->setController(self::class)
                ->setAction('index')
                ->generateUrl();

        return $this->redirect($url);
    }

    
    // L'admin refuse
    public function refuseRequest(AdminContext $context, EntityManagerInterface $em): Response
    {
        /** @var Commande $commande */
        $commande = $context->getEntity()->getInstance();

        // On change le statut de la demande
        $commande->setDemandeModificationStatut('refused');

        // On sauvegarde en BDD
        $em->flush();

        // Message flash
        $this->addFlash('danger', 'La demande de modification a été refusée.');

        // Redirection vers la page précédente ou fallback sur l'index
        $url = $context->getReferrer() ?? $this->generateUrl('admin', [
            'crudAction' => 'index',
            'entityFqcn' => Commande::class,
        ]);

        return $this->redirect($url);
    }
}