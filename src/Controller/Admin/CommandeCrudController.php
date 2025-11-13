<?php

namespace App\Controller\Admin;

// En haut du fichier (imports)
use Doctrine\ORM\PersistentCollection;
use App\Entity\Commande;
use App\Entity\Produit;
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

use App\Entity\Devis;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;

use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore; // Important
use Symfony\Component\Form\FormBuilderInterface; // Important
use Symfony\Component\Form\FormEvent; // Important
use Symfony\Component\Form\FormEvents; // Important

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
        /*
        $exportPdf = Action::new('exportPdf', 'PDF', 'fa fa-file-pdf')
            ->linkToUrl(function (Commande $commande) {
                return $this->generateUrl('admin_export_facture', ['id' => $commande->getId()]);
            })
            ->setHtmlAttributes(['target' => '_blank'])
            ->setCssClass('btn btn-secondary btn-sm');
        */

        $actions = $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            //->add(Crud::PAGE_INDEX, $exportPdf)
            //->add(Crud::PAGE_DETAIL, $exportPdf)
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) =>
                $action->displayIf(fn () => $this->isGranted('ROLE_ADMIN'))
            )
            ->update(Crud::PAGE_DETAIL, Action::DELETE, fn (Action $action) =>
                $action->displayIf(fn () => $this->isGranted('ROLE_ADMIN'))
            );

        // ⚡ Ici on enlève le bouton "Supprimer la sélection" pour les commerciaux
        if (!$this->isGranted('ROLE_ADMIN')) {
            $actions->disable(Action::BATCH_DELETE);
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
            ->add(ChoiceFilter::new('paoBatValidation', 'Statut Pao')->setChoices([
                'En attente de validation' => 'En attente de validation',
                'Modification à faire' => 'Modification à faire',
                'Valider pour la production' => 'Valider pour la production',
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

        if ($commande->getDevisOrigine() !== null) {
            $commande->setStatutDevis(Commande::STATUT_DEVIS_VALIDEE);
        } else {
            $commande->setStatutDevis(Commande::STATUT_DEVIS_NON_VALIDEE);
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

        // Si un devis est sélectionné, statutDevis = Validée
        if ($commande->getDevisOrigine() !== null) {
            $commande->setStatutDevis(Commande::STATUT_DEVIS_VALIDEE);
        } else {
            $commande->setStatutDevis(Commande::STATUT_DEVIS_NON_VALIDEE);
        }
        
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

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Commande) {
            return;
        }

        if (null === $entityInstance->getClient()) {
            $this->addFlash('danger', 'La commande n\'a pas pu être créée car aucun client n\'a été sélectionné ou créé.');
            return; 
        }

        // Récupération des données brutes du formulaire
        $formData = $this->requestStack->getCurrentRequest()->request->all()['Commande'] ?? [];
        $referencePaiement = $formData['referencePaiement'] ?? null;
        $detailsPaiement = $formData['detailsPaiement'] ?? null;

        // Si aucune référence fournie, mettre "Espèces" par défaut
        if (empty($referencePaiement)) {
            $referencePaiement = 'Espèce';
        }

        // Génération des paiements
        $entityInstance->genererPaiementsAutomatiques($referencePaiement, $detailsPaiement);

        $this->addFlash('success', 'La commande a été créée');

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Commande) {
            return;
        }

        if (null === $entityInstance->getClient()) {
            $this->addFlash('danger', 'La commande n\'a pas pu être mise à jour car aucun client n\'a été sélectionné ou créé.');
            return;
        }

        // 1. Récupération des données brutes du formulaire
        $formData = $this->requestStack->getCurrentRequest()->request->all()['Commande'] ?? [];
        $referencePaiement = $formData['referencePaiement'] ?? null;
        $detailsPaiement = $formData['detailsPaiement'] ?? null;
        $nouvelleMethodePaiement = $formData['methodePaiement'] ?? null;

        // 2. Si aucune référence fournie, mettre "Espèce" par défaut
        if (empty($referencePaiement)) {
            $referencePaiement = 'Espèce';
        }

        // 3. Mettre à jour la méthode de paiement si elle a changé
        if ($nouvelleMethodePaiement) {
            $entityInstance->setMethodePaiement($nouvelleMethodePaiement);
        }

        // 4. Recalcul des paiements automatiques
        $entityInstance->recalculerPaiementsPourUpdate();

        $this->addFlash('success', 'La commande a été mise à jour.');

        parent::updateEntity($entityManager, $entityInstance);
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

    // === ON AJOUTE LE FILTRAGE DE LA LISTE ===
    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        // Si l'utilisateur est un commercial (et pas un admin), on applique le filtre.
        // L'admin, lui, doit tout voir.
        if ($this->isGranted('ROLE_COMMERCIAL') && !$this->isGranted('ROLE_ADMIN')) {
            $user = $this->getUser();
            $qb->andWhere('entity.commercial = :currentUser')
               ->setParameter('currentUser', $user);
        }

        return $qb;
    }

    public function createEntity(string $entityFqcn)
    {
        $commande = new Commande();
        // On assigne l'utilisateur actuellement connecté comme commercial
        $commande->setCommercial($this->getUser());
        
        return $commande;
    }
    
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('devisOrigine', 'Lier un devis validé')
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('placeholder', 'Sélectionnez un devis pour tout remplir')
            ->setHelp('Remplira automatiquement le client, les produits et les conditions de paiement.')
            ->setQueryBuilder(function (QueryBuilder $qb) {
                // 1. On récupère la commande en cours d'édition (si on est en mode édition)
                $currentCommande = $this->getContext()->getEntity()->getInstance();
                $currentDevisId = null;
                if ($currentCommande && $currentCommande->getDevisOrigine()) {
                    $currentDevisId = $currentCommande->getDevisOrigine()->getId();
                }

                // 2. Sous-requête pour trouver les devis déjà utilisés (comme avant)
                $commandeQb = $this->entityManager->createQueryBuilder();
                $subQuery = $commandeQb
                    ->select('IDENTITY(c.devisOrigine)')
                    ->from(Commande::class, 'c')
                    ->where('c.devisOrigine IS NOT NULL')
                    ->getDQL();

                // 3. Construction de la condition WHERE principale
                // Condition A : Le devis doit avoir le statut 'BAT/Production'
                $conditionStatut = 'entity.statut = :statut';
                $qb->setParameter('statut', Devis::STATUT_BAT_PRODUCTION);

                // Condition B : Le devis ne doit PAS être dans la liste des devis déjà utilisés
                $conditionNotIn = $qb->expr()->notIn('entity.id', $subQuery);
                
                // Condition C (POUR L'ÉDITION) : OU le devis doit être celui déjà lié à la commande actuelle
                if ($currentDevisId) {
                    $conditionCurrentDevis = 'entity.id = :currentDevisId';
                    $qb->setParameter('currentDevisId', $currentDevisId);
                    
                    // On combine les conditions : (A ET (B OU C))
                    $qb->andWhere($conditionStatut)
                    ->andWhere($qb->expr()->orX($conditionNotIn, $conditionCurrentDevis));
                } else {
                    // Si on est en mode création (pas de devis actuel), on applique simplement les conditions A et B
                    $qb->andWhere($conditionStatut)
                    ->andWhere($conditionNotIn);
                }

                return $qb;
            });

        yield TextField::new('bonDeCommande', 'N° de bon de commande')
            ->setRequired(false)
            ->hideOnIndex();

        yield DateTimeField::new('dateCommande', 'Date de Commande')
            ->setFormat('dd/MM/yyyy HH:mm')
            //->setFormTypeOption('data', new \DateTime()) // valeur par défaut
            ->onlyOnForms();

        yield DateTimeField::new('dateCommande', 'Date de Commande')
            ->hideOnForm();

        // Affichage standard sur index/detail
        yield AssociationField::new('client')
            ->hideOnForm();

        yield CollectionField::new('commandeProduits', 'Produits commandés')
            ->setCssClass('field-from-devis')
            //->setLabel(false) // Le label est déjà dans le panneau
            ->setEntryType(CommandeProduitType::class) // C'est ici que la magie opère
            ->setFormTypeOptions(['by_reference' => false])
            ->setEntryIsComplex(true)
            ->allowAdd()
            //->hideOnIndex()
            ->allowDelete();
        
        if (Crud::PAGE_EDIT === $pageName) {
            yield CollectionField::new('commandeProduits', 'Produits commandés')
                //->setLabel(false) // Le label est déjà dans le panneau
                ->setEntryType(CommandeProduitType::class) // C'est ici que la magie opère
                ->setFormTypeOptions(['by_reference' => false])
                ->setEntryIsComplex(true)
                ->allowAdd()
                //->hideOnIndex()
                ->allowDelete();
        }

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
            
        if ($this->isGranted('ROLE_COMMERCIAL') && !$this->isGranted('ROLE_ADMIN')) {
            yield TextField::new('commercial.username', 'Commercial')
                ->setFormTypeOption('disabled', true)
                ->onlyOnForms();
        }

        yield AssociationField::new('pao', 'Responsable PAO')
            ->setCssClass('field-from-devis')
            ->setQueryBuilder(function (QueryBuilder $qb) {
                $alias = $qb->getRootAliases()[0];
                return $qb
                    ->andWhere(sprintf('%s.roles LIKE :role', $alias))
                    ->setParameter('role', '%"ROLE_PAO"%')
                    // On remplace 'email' par 'username' pour le tri
                    ->orderBy(sprintf('%s.username', $alias), 'ASC');
            })
            ->hideOnIndex();
        
        yield AssociationField::new('production', 'Responsable Production')
            ->setQueryBuilder(function (QueryBuilder $qb) {
                // On ne liste que les utilisateurs avec le ROLE_PRODUCTION
                $alias = $qb->getRootAliases()[0];
                return $qb
                    ->andWhere(sprintf('%s.roles LIKE :role', $alias))
                    ->setParameter('role', '%"ROLE_PRODUCTION"%')
                    ->orderBy(sprintf('%s.username', $alias), 'ASC');
            })
            ->hideOnIndex();

        // SI on est sur la page de CRÉATION (new)
        if (Crud::PAGE_NEW === $pageName) {
            yield Field::new('clientSelector', 'Client')
                ->setCssClass('field-from-devis')
                ->setFormType(ClientOrNewClientType::class)
                ->setRequired(true)
                ->setFormTypeOptions([
                    'label' => false,
                    'mapped' => false, 
                ]);
        }

        // SI on est sur la page de MODIFICATION (edit)
        if (Crud::PAGE_EDIT === $pageName) {
            yield FormField::addPanel('Informations du Client')->setCssClass('field-from-devis');;
            yield AssociationField::new('client', 'Client');
                //->setFormTypeOption('disabled', true); // Grise le champ pour qu'il ne soit pas modifiable
        }

        yield ChoiceField::new('methodePaiement', 'Méthode de paiement')
            ->setCssClass('field-from-devis')
            ->setChoices([
                '50% à la commande, 50% à la livraison' => '50% commande, 50% livraison',
                '100% à la livraison' => '100% livraison',
                '30 jours après réception de la facture' => '30 jours fin de mois',
                '100% à la commande' => '100% commande'
            ])
            ->setHelp('Choisissez les conditions de règlement.');

        /*yield FormField::addPanel('')->setCssClass('dynamic-moyen-paiement-wrapper')->setLabel(false);
        yield ChoiceField::new('referencePaiement', 'Moyen de paiement')
            ->setChoices([
                'Espèces' => 'Espèces',
                'Carte Bancaire' => 'Carte Bancaire',
                'Mobile Money' => 'Mobile Money',
                'Virement Bancaire' => 'Virement Bancaire',
                'Chèque' => 'Chèque',
            ])
            ->onlyOnIndex()
            ->setHelp('Sera utilisé pour le premier paiement généré.')
            ->setFormTypeOption('mapped', false)
            ->hideWhenUpdating();

        yield FormField::addPanel('')->setCssClass('dynamic-details-paiement-wrapper')->setLabel(false);
        yield TextField::new('detailsPaiement', 'Détails / Références')
            ->setHelp('Ex: N° de chèque, référence de virement...')
            ->setFormTypeOption('mapped', false)
            ->hideWhenUpdating();*/

        // =======================================================
        // ====== JAVASCRIPT CORRIGÉ CI-DESSOUS ======
        // =======================================================
        /*yield FormField::addPanel('')->setHelp(<<<'HTML'
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // On sélectionne nos champs et wrappers
                const methodePaiementSelect = document.querySelector('#Commande_methodePaiement');
                const moyenPaiementWrapper = document.querySelector('.dynamic-moyen-paiement-wrapper');
                
                // MODIFIÉ : On cible le nouvel ID du champ et on renomme la variable pour plus de clarté
                const referencePaiementSelect = document.querySelector('#Commande_referencePaiement'); 
                
                const detailsPaiementWrapper = document.querySelector('.dynamic-details-paiement-wrapper');

                // MODIFIÉ : On met à jour la condition de sécurité
                if (!methodePaiementSelect || !moyenPaiementWrapper || !detailsPaiementWrapper || !referencePaiementSelect) {
                    return; // Sécurité si un élément est manquant
                }

                function toggleDynamicFields() {
                    // --- Logique pour afficher/masquer le moyen de paiement ---
                    const methodeValue = methodePaiementSelect.value;
                    const showMoyenPaiement = methodeValue === '100% commande' || methodeValue === '50% commande, 50% livraison';
                    
                    moyenPaiementWrapper.style.display = showMoyenPaiement ? 'block' : 'none';

                    // --- Logique pour afficher/masquer les détails ---
                    // MODIFIÉ : On utilise la nouvelle variable
                    const referenceValue = referencePaiementSelect.value; 
                    
                    // On affiche les détails si le moyen de paiement est visible ET que ce n'est pas "Espèces"
                    const showDetails = showMoyenPaiement && referenceValue && referenceValue !== 'Espèces';

                    detailsPaiementWrapper.style.display = showDetails ? 'block' : 'none';
                }

                // On attache les écouteurs d'événements
                methodePaiementSelect.addEventListener('change', toggleDynamicFields);
                
                // MODIFIÉ : On attache l'écouteur au bon élément
                referencePaiementSelect.addEventListener('change', toggleDynamicFields); 

                // On exécute la fonction une fois au chargement pour définir l'état initial
                toggleDynamicFields()
            });
            </script>
        HTML)->setCssClass('d-none'); // On cache ce panel qui ne sert qu'à porter le script*/

        yield MoneyField::new('fraisLivraison', 'Frais de livraison')
            ->setCurrency('MGA')
            ->setStoredAsCents(false)
            ->setNumDecimals(0);
            //->hideOnIndex();

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
            
            // On revient à la méthode qui fonctionne pour TOUS les champs.
            ->setCustomOption('renderAsHtml', true)
            
            ->hideOnForm();
        
        if (Crud::PAGE_EDIT === $pageName) {
            yield CollectionField::new('paiements', 'Paiements')
                ->setEntryType(PaiementType::class)
                ->setFormTypeOptions(['by_reference' => false])
                ->allowAdd()
                ->allowDelete();
        }

        if (Crud::PAGE_NEW === $pageName) {
            yield CollectionField::new('paiements', 'Paiements')
                ->setCssClass('field-from-devis')
                ->setEntryType(PaiementType::class)
                ->setFormTypeOptions(['by_reference' => false])
                ->allowAdd()
                ->allowDelete();
        }

        yield FormField::addPanel('Validation PAO / Bon à Tirer (BAT)')->collapsible()
            ->setHelp('Vous disposez de 3 cycles de modification. Après la 3ème modification, la validation sera automatique.');

        // Le commercial voit le travail du PAO en lecture seule
        yield BooleanField::new('paoFichierOk', 'Fichier OK ?')->setFormTypeOption('disabled', true);
        yield BooleanField::new('paoBatOk', 'BAT Prêt ?')->setFormTypeOption('disabled', true);

        // Suivi des cases cochées par le PAO
        yield BooleanField::new('paoModif1Ok', 'Modif 1 Faite')->setFormTypeOption('disabled', true);
        yield BooleanField::new('paoModif2Ok', 'Modif 2 Faite')->setFormTypeOption('disabled', true);
        yield BooleanField::new('paoModif3Ok', 'Modif 3 Faite')->setFormTypeOption('disabled', true);

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

        yield Field::new('modificationFile', 'Pièce jointe de modification (optionnel)')
            ->setFormType(VichFileType::class)
            ->setFormTypeOption('allow_delete', false) // Optionnel: empêche la suppression facile
            ->setHelp('Joindre un fichier pour illustrer la modification (PDF, image...).')
            ->setCssClass('bat-pj-field')
            ->onlyOnForms();

        yield Field::new('modificationFile2', 'Pièce jointe de modification 2 (optionnel)')
            ->setFormType(VichFileType::class)
            ->setFormTypeOption('allow_delete', false) // Optionnel: empêche la suppression facile
            ->setHelp('Joindre un fichier pour illustrer la modification (PDF, image...).')
            ->setCssClass('bat-pj-field-deux')
            ->onlyOnForms();

        yield Field::new('modificationFile3', 'Pièce jointe de modification 3 (optionnel)')
            ->setFormType(VichFileType::class)
            ->setFormTypeOption('allow_delete', false) // Optionnel: empêche la suppression facile
            ->setHelp('Joindre un fichier pour illustrer la modification (PDF, image...).')
            ->setCssClass('bat-pj-field-trois')
            ->onlyOnForms();
                
        // Champ de SAISIE pour la PROCHAINE modification
        yield TextareaField::new('paoMotifModification', 'Motif de la modification à faire')
            ->setHelp('À remplir OBLIGATOIREMENT si vous demandez une modification.')
            ->setCssClass('bat-motif-field');

        // Pour afficher le fichier sur les pages index/detail
        yield TextField::new('modificationFileName', 'Pièce jointe modif.')
            ->formatValue(function ($value, $entity) {
                if (!$value) {
                    return null;
                }
                return sprintf(
                    '<a href="/uploads/fichiers/commandes/%s" target="_blank">📂 Voir le fichier</a>',
                    $value
                );
            })
            ->hideOnForm()
            ->renderAsHtml();

        // Pour afficher le fichier sur les pages index/detail
        yield TextField::new('modificationFileName2', 'Pièce jointe modif2.')
            ->formatValue(function ($value, $entity) {
                if (!$value) {
                    return null;
                }
                return sprintf(
                    '<a href="/uploads/fichiers/commandes2/%s" target="_blank">📂 Voir le fichier</a>',
                    $value
                );
            })
            ->hideOnForm()
            ->renderAsHtml();

        // Pour afficher le fichier sur les pages index/detail
        yield TextField::new('modificationFileName3', 'Pièce jointe modif3.')
            ->formatValue(function ($value, $entity) {
                if (!$value) {
                    return null;
                }
                return sprintf(
                    '<a href="/uploads/fichiers/commandes3/%s" target="_blank">📂 Voir le fichier</a>',
                    $value
                );
            })
            ->hideOnForm()
            ->renderAsHtml();

        // Affichage de l'HISTORIQUE des motifs (lecture seule)
        yield TextareaField::new('paoMotifM1', 'Motif Modif. 1')->setFormTypeOption('disabled', true);
        yield TextareaField::new('paoMotifM2', 'Motif Modif. 2')->setFormTypeOption('disabled', true);
        yield TextareaField::new('paoMotifM3', 'Motif Modif. 3')->setFormTypeOption('disabled', true);

        // Upload (formulaire)
        yield TextField::new('pieceJointeFile', 'Pièce jointe 1')
            ->setFormType(VichFileType::class)
            ->setHelp('Joindre un fichier(PDF, image...).')
            ->onlyOnForms();

        // Affichage (index/detail) → lien cliquable
        yield TextField::new('pieceJointe', 'Pièce jointe 1')
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

        // Upload (formulaire)
        yield TextField::new('pieceJointeFile2', 'Pièce jointe 2')
            ->setFormType(VichFileType::class)
            ->setHelp('Joindre un fichier(PDF, image...).')
            ->onlyOnForms();

        // Affichage (index/detail) → lien cliquable
        yield TextField::new('pieceJointe2', 'Pièce jointe 2')
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

        // Upload (formulaire)
        yield TextField::new('pieceJointeFile3', 'Pièce jointe 3')
            ->setFormType(VichFileType::class)
            ->setHelp('Joindre un fichier(PDF, image...).')
            ->onlyOnForms();

        // Affichage (index/detail) → lien cliquable
        yield TextField::new('pieceJointe3', 'Pièce jointe 3')
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

        yield FormField::addPanel('')->setHelp(<<<'HTML'
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const batValidationSelect = document.querySelector('#Commande_paoBatValidation');
                if (!batValidationSelect) return; // sans select, on stoppe

                // Récupère les wrappers si présents (closest('.form-group') si dispo, sinon l'élément lui-même)
                const getWrapper = (selector) => {
                    const el = document.querySelector(selector);
                    if (!el) return null;
                    return el.closest ? (el.closest('.form-group') || el) : el;
                };

                const motifWrapper  = getWrapper('.bat-motif-field');
                const pjWrapper1    = getWrapper('.bat-pj-field');
                const pjWrapper2    = getWrapper('.bat-pj-field-deux');
                const pjWrapper3    = getWrapper('.bat-pj-field-trois');

                function toggleMotifField() {
                    // Valeurs acceptées (pool de valeurs possibles pour tolérance)
                    const v = batValidationSelect.value;
                    // DEBUG : activez la ligne suivante temporairement si vous voulez voir la valeur dans la console
                    // console.log('Commande_paoBatValidation value =', v);

                    const shouldShow = v === 'Modification demandée' || v === 'Modification à faire' || v.toLowerCase().includes('modification');

                    // Pour chaque wrapper, on applique la visibilité seulement s'il existe
                    [motifWrapper, pjWrapper1, pjWrapper2, pjWrapper3].forEach(w => {
                        if (!w) return;
                        w.style.display = shouldShow ? 'block' : 'none';
                    });
                }

                // Appels initiaux et listeners (Turbo-friendly)
                toggleMotifField();
                batValidationSelect.addEventListener('change', toggleMotifField);
                document.addEventListener('turbo:load', toggleMotifField);
            });
            </script>
            HTML
            )->setCssClass('d-none');


        yield FormField::addPanel('')->setHelp(<<<HTML
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Fonction pour mettre à jour le prix
                    const updatePrice = (selectElement) => {
                        if (selectElement.selectedIndex < 0) {
                            return; // Pas d'option sélectionnée, on ne fait rien
                        }
                        const selectedOption = selectElement.options[selectElement.selectedIndex];
                        if (selectElement.selectedIndex < 0) {
                            return; // Pas d'option sélectionnée, on ne fait rien
                        }
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

        yield ChoiceField::new('statutDevis', 'Statut Devis')
            ->setChoices([
                'Validée' => Commande::STATUT_DEVIS_VALIDEE,
                'Non validée' => Commande::STATUT_DEVIS_NON_VALIDEE,
            ])
            ->setDisabled(true)
            ->hideOnForm()
            ->renderAsBadges([
                Commande::STATUT_DEVIS_NON_VALIDEE => 'danger',
                Commande::STATUT_DEVIS_VALIDEE => 'success',
            ]);

        // Injection du JS directement dans EasyAdmin via un champ invisible
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
        
        yield FormField::addPanel('')->setHelp(<<<'HTML'
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // On s'assure que le script s'exécute aussi après les rechargements de Turbo
                document.addEventListener('turbo:load', setupDevisToggle);
                
                function setupDevisToggle() {
                    const devisSelect = document.querySelector('#Commande_devisOrigine');
                    if (!devisSelect) {
                        return; // Pas sur la bonne page
                    }

                    // La fonction qui fait tout le travail
                    function toggleAutoFilledFields() {
                        // On vérifie si un devis est sélectionné (s'il y a une valeur non vide)
                        const isDevisLinked = !!devisSelect.value;
                        
                        // On récupère tous les champs qu'on a marqués
                        const fieldsToToggle = document.querySelectorAll('.field-from-devis');

                        fieldsToToggle.forEach(field => {
                            // EasyAdmin enveloppe chaque champ dans un conteneur .form-group
                            // C'est ce conteneur qu'il faut masquer pour cacher aussi le label
                            const wrapper = field.closest('.form-group, .accordion');
                            if (wrapper) {
                                wrapper.style.display = isDevisLinked ? 'none' : 'block';
                            }
                        });
                    }

                    // On écoute les changements sur le sélecteur de devis
                    devisSelect.addEventListener('change', toggleAutoFilledFields);

                    // On exécute la fonction une fois au chargement pour définir l'état initial
                    // (important pour la page d'édition)
                    toggleAutoFilledFields();
                }

                // Premier appel
                setupDevisToggle();
            });
            </script>
            HTML)->setCssClass('d-none'); // Cache le panneau qui ne sert qu'à porter le script
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

    public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $formBuilder = parent::createNewFormBuilder($entityDto, $formOptions, $context);
        // On attache notre écouteur d'événement personnalisé au formulaire de création
        return $this->addDevisDataListener($formBuilder);
    }

    private function addDevisDataListener(FormBuilderInterface $formBuilder): FormBuilderInterface
    {
        $formBuilder->addEventListener(FormEvents::PRE_SUBMIT, function(FormEvent $event) {
            // 1. On récupère les données brutes soumises par le formulaire
            $data = $event->getData();
            
            // 2. On récupère l'ID du devis qui a été sélectionné
            $devisId = $data['devisOrigine'] ?? null;
            if (!$devisId) {
                return; // Si aucun devis n'est sélectionné, on ne fait rien
            }
            
            // 3. On va chercher l'entité Devis complète depuis la base de données
            /** @var Devis|null $devis */
            $devis = $this->entityManager->getRepository(Devis::class)->find($devisId);
            
            if (!$devis) {
                return; // Si le devis n'est pas trouvé, on ne fait rien
            }

            // 4. On modifie le tableau de données ($data) pour pré-remplir la commande

            // a. Pré-remplir le client
            if ($devis->getClient()) {
                if (!isset($data['clientSelector'])) {
                    $data['clientSelector'] = [];
                }
                $data['clientSelector']['choice'] = 'existing';
                $data['clientSelector']['existingClient'] = $devis->getClient()->getId();
            }

            // b. Pré-remplir les lignes de produits
            $data['commandeProduits'] = [];
            $produitRepository = $this->entityManager->getRepository(Produit::class);
            $lignesAjoutees = 0;
            foreach ($devis->getLignes() as $devisLigne) {
                $produitTrouve = $produitRepository->findOneBy(['nom' => $devisLigne->getDescriptionProduit()]);
                if ($produitTrouve) {
                    $data['commandeProduits'][(string)$lignesAjoutees] = [
                        'produit' => $produitTrouve->getId(),
                        'quantite' => $devisLigne->getQuantite(),
                        'prixUnitaire' => $devisLigne->getPrixUnitaire(),
                    ];
                    $lignesAjoutees++;
                } else {
                    $this->addFlash('warning', sprintf(
                        'Le produit "%s" du devis n\'a pas été trouvé dans la base de données et n\'a pas été ajouté à la commande.',
                        $devisLigne->getDescriptionProduit()
                    ));
                }
            }

            // c. Pré-remplir les conditions et détails de paiement
            if ($devis->getMethodePaiement()) {
                $methodeMap = [
                    Devis::METHODE_100_COMMANDE => '100% commande',
                    Devis::METHODE_50_50 => '50% commande, 50% livraison',
                    Devis::METHODE_100_LIVRAISON => '100% livraison',
                    Devis::METHODE_30_JOURS => '30 jours fin de mois',
                ];
                $data['methodePaiement'] = $methodeMap[$devis->getMethodePaiement()] ?? null;
            }
            
            // ========================================================== //
            // ====== NOUVEAUX AJOUTS ====== //
            // ========================================================== //
            
            // d. Pré-remplir le PAO en charge
            // On vérifie que le devis a un PAO assigné
            if ($devis->getPao()) {
                // Le champ du formulaire attend l'ID de l'utilisateur PAO
                $data['pao'] = $devis->getPao()->getId();
            }
            
            // e. Pré-remplir le MOYEN de paiement (le champ s'appelle referencePaiement dans votre formulaire)
            // Ce champ est 'mapped' => false, mais on peut quand même le remplir
            if ($devis->getModeDePaiement()) {
                $data['referencePaiement'] = $devis->getModeDePaiement();
            }

            // f. Pré-remplir les DÉTAILS / RÉFÉRENCES de paiement
            // Ce champ est aussi 'mapped' => false
            if ($devis->getDetailsPaiement()) {
                $data['detailsPaiement'] = $devis->getDetailsPaiement();
            }

            // ========================================================== //
            // ====== FIN DES NOUVEAUX AJOUTS ====== //
            // ========================================================== //

            // 5. On remplace les données de l'événement par nos données modifiées
            $event->setData($data);
        });
        
        return $formBuilder;
    }
}