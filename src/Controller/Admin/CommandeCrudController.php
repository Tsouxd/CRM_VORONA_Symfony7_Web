<?php

namespace App\Controller\Admin;

use App\Entity\Commande;
use App\Entity\Paiement;
use App\Entity\CommandeProduit;
use Doctrine\ORM\EntityManagerInterface;
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
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\HttpFoundation\RequestStack;

class CommandeCrudController extends AbstractCrudController implements EventSubscriberInterface
{
    private $commandeOriginalData = [];
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
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
        $exportPdf = Action::new('exportPdf', '🧾 Exporter PDF')
            ->linkToUrl(function (Commande $commande) {
                return $this->generateUrl('admin_export_facture', ['id' => $commande->getId()]);
            })
            ->setHtmlAttributes([
                'target' => '_blank',
                'class' => 'btn btn-secondary',
            ]);

        return $actions
            ->add(Crud::PAGE_INDEX, $exportPdf)
            ->add(Crud::PAGE_DETAIL, $exportPdf)
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action
                    ->setLabel('Supprimer')
                    ->setIcon('fa fa-trash');
            })
            ->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                return $action
                    ->setLabel('Modifier')
                    ->setIcon('fa fa-pen');
            });
    }
    
    public static function getEntityFqcn(): string
    {
        return Commande::class;
    }

    /**
     * Récupère la commande originale depuis la base de données
     */
    private function getOriginalCommande(EntityManagerInterface $entityManager, Commande $commande): ?Commande
    {
        if ($commande->getId() === null) {
            return null; // Nouvelle commande
        }
        
        // Récupérer l'entité originale depuis la base de données
        return $entityManager->getRepository(Commande::class)->find($commande->getId());
    }

    /**
     * Événement avant la mise à jour d'une commande
     */
    public function beforeUpdate(BeforeEntityUpdatedEvent $event)
    {
        $entity = $event->getEntityInstance();
        
        if (!$entity instanceof Commande) {
            return;
        }
        
        $entityManager = $this->container->get('doctrine')->getManager();
        $session = $this->requestStack->getSession();
        
        // Récupérer l'entité originale depuis la base de données
        $originalCommande = $this->getOriginalCommande($entityManager, $entity);
        
        if ($originalCommande === null) {
            return;
        }

        // Sauvegarder les données originales pour les produits
        $originalCommandeProduits = [];
        foreach ($originalCommande->getCommandeProduits() as $cp) {
            $produitId = $cp->getProduit()->getId();
            $originalCommandeProduits[$produitId] = [
                'quantite' => $cp->getQuantite(),
                'produit' => $cp->getProduit()
            ];
        }
        
        // Stocke la liste des produits originaux dans la session
        $session->set('original_commande_produits', $originalCommandeProduits);
        
        // Analyser les modifications de produits
        $this->processProductChanges($entity, $originalCommandeProduits, $entityManager);
    }

    /**
     * Traite les changements de produits entre la commande originale et la nouvelle
     */
    private function processProductChanges(Commande $commande, array $originalCommandeProduits, EntityManagerInterface $entityManager)
    {
        $seuilFaibleStock = 10;
        $stockSuffisant = true;
        $produitsInsuffisants = [];
        $produitsFaibleStock = [];
        
        // Liste pour suivre les changements de stock à appliquer
        $changementsStock = [];
        $nouveauxCommandeProduits = [];
        
        // Indexer les nouveaux produits pour faciliter la comparaison
        foreach ($commande->getCommandeProduits() as $cp) {
            $produitId = $cp->getProduit()->getId();
            $nouveauxCommandeProduits[$produitId] = $cp;
        }
        
        // 1. Vérifier les produits qui ont été modifiés ou ajoutés
        foreach ($nouveauxCommandeProduits as $produitId => $cp) {
            $produit = $cp->getProduit();
            $nouvelleQuantite = $cp->getQuantite();
            
            // Déterminer si ce produit existait dans la commande originale
            $ancienneQuantite = 0;
            if (isset($originalCommandeProduits[$produitId])) {
                $ancienneQuantite = $originalCommandeProduits[$produitId]['quantite'];
            }
            
            // Calculer la différence
            $difference = $nouvelleQuantite - $ancienneQuantite;
            
            // Si la quantité augmente, vérifier si le stock est suffisant
            if ($difference > 0) {
                if ($produit->getStock() < $difference) {
                    $stockSuffisant = false;
                    $produitsInsuffisants[] = $produit->getNom() . ' (Demandé en plus: ' . $difference . ', Disponible: ' . $produit->getStock() . ')';
                } else {
                    // Stock suffisant, appliquer l'ajustement
                    $nouveauStock = $produit->getStock() - $difference;
                    $produit->setStock($nouveauStock);
                    $entityManager->persist($produit);
                    
                    // Vérifier si le stock restant sera faible
                    if ($nouveauStock <= $seuilFaibleStock) {
                        $produitsFaibleStock[] = $produit->getNom() . ' (Stock restant: ' . $nouveauStock . ')';
                    }
                }
            } elseif ($difference < 0) {
                // Si la quantité diminue, on rend du stock
                $nouveauStock = $produit->getStock() + abs($difference);
                $produit->setStock($nouveauStock);
                $entityManager->persist($produit);
            }
        }
        
        // 2. Traiter les produits qui ont été retirés de la commande (rendus au stock)
        foreach ($originalCommandeProduits as $produitId => $data) {
            if (!isset($nouveauxCommandeProduits[$produitId])) {
                $produit = $data['produit'];
                $quantite = $data['quantite'];
                
                // Rendre au stock la quantité qui avait été réservée
                $nouveauStock = $produit->getStock() + $quantite;
                $produit->setStock($nouveauStock);
                $entityManager->persist($produit);
            }
        }
        
        // Si le stock est insuffisant, ajouter un message flash et ne pas continuer
        if (!$stockSuffisant) {
            $messageErreur = 'Stock insuffisant pour les produits suivants : ' . implode(', ', $produitsInsuffisants);
            $this->addFlash('danger', $messageErreur);
            throw new \Exception($messageErreur);
        }
        
        // Afficher une alerte pour les produits à faible stock
        if (!empty($produitsFaibleStock)) {
            $messageAlerte = 'Attention : Faible stock pour les produits suivants : ' . implode(', ', $produitsFaibleStock);
            $this->addFlash('warning', $messageAlerte);
        }
        
        // Recalculer le total de la commande
        $total = 0.0;
        foreach ($commande->getCommandeProduits() as $cp) {
            $total += $cp->getQuantite() * $cp->getProduit()->getPrix();
        }
    }

    /*public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Commande) {
            return;
        }

        $statutCommande = $entityInstance->getStatut();

        foreach ($entityInstance->getPaiements() as $paiement) {
            switch ($statutCommande) {
                case 'annulée':
                    $paiement->setStatut('annulée');
                    break;

                case 'en attente':
                    $paiement->setStatut('en attente');
                    break;

                case 'en cours':
                    $paiement->setStatut('en cours');
                    break;

                case 'livrée':
                    $paiement->setStatut('payée');
                    break;
            }

            $entityManager->persist($paiement);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }*/

    /**
     * Événement avant la persistance d'une nouvelle commande
     */
    public function beforePersist(BeforeEntityPersistedEvent $event)
    {
        $entity = $event->getEntityInstance();
        
        if (!$entity instanceof Commande) {
            return;
        }
        
        $entityManager = $this->container->get('doctrine')->getManager();
        
        $stockSuffisant = true;
        $produitsInsuffisants = [];
        $produitsFaibleStock = [];
        $seuilFaibleStock = 10;
        
        // Vérifier d'abord si le stock est suffisant pour tous les produits
        foreach ($entity->getCommandeProduits() as $cp) {
            $produit = $cp->getProduit();
            $quantite = $cp->getQuantite();
            
            if ($produit->getStock() < $quantite) {
                $stockSuffisant = false;
                $produitsInsuffisants[] = $produit->getNom() . ' (Demandé: ' . $quantite . ', Disponible: ' . $produit->getStock() . ')';
            }
        }
        
        // Si le stock est insuffisant, ajouter un message flash et ne pas continuer
        if (!$stockSuffisant) {
            $messageErreur = 'Stock insuffisant pour les produits suivants : ' . implode(', ', $produitsInsuffisants);
            $this->addFlash('danger', $messageErreur);
            throw new \Exception($messageErreur);
        }
        
        // Sinon, procéder normalement
        $total = 0.0;
        foreach ($entity->getCommandeProduits() as $cp) {
            $produit = $cp->getProduit();
            $quantite = $cp->getQuantite();
            $nouveauStock = $produit->getStock() - $quantite;
        
            // Vérifier si le stock passe sous le seuil d'alerte
            if ($nouveauStock <= $seuilFaibleStock) {
                $produitsFaibleStock[] = $produit->getNom() . ' (Stock restant: ' . $nouveauStock . ')';
            }
        
            $produit->setStock($nouveauStock);
            $entityManager->persist($produit);
        
            $total += $quantite * $produit->getPrix();
        }
        
        // Afficher une alerte pour les produits à faible stock
        if (!empty($produitsFaibleStock)) {
            $messageAlerte = 'Attention : Faible stock pour les produits suivants : ' . implode(', ', $produitsFaibleStock);
            $this->addFlash('warning', $messageAlerte);
        }
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Commande) {
            parent::deleteEntity($entityManager, $entityInstance);
            return;
        }
        
        // Restaurer le stock pour chaque produit de la commande
        foreach ($entityInstance->getCommandeProduits() as $cp) {
            $produit = $cp->getProduit();
            $quantite = $cp->getQuantite();
            
            // Augmenter le stock de la quantité qui avait été réservée
            $produit->setStock($produit->getStock() + $quantite);
            $entityManager->persist($produit);
        }
        
        // Supprimer l'entité
        parent::deleteEntity($entityManager, $entityInstance);
    }
    
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            DateTimeField::new('dateCommande', 'Date de Commande')
                ->setFormat('dd/MM/yyyy HH:mm'),
            AssociationField::new('client', 'Client')
                ->autocomplete(),
            CollectionField::new('paiements', 'Paiement')
                ->setFormTypeOption('disabled', true),
            CollectionField::new('commandeProduits')
                ->setFormTypeOption('disabled', true)
                ->setEntryIsComplex(true)
                //->setEntryType(\App\Form\CommandeProduitType::class)
                ->allowAdd()
                ->allowDelete(),
            ChoiceField::new('statut')
                ->setChoices([
                    'En attente' => 'en attente',
                    'En cours' => 'en cours',
                    'Livrée' => 'livrée',
                    'Annulée' => 'annulée',
                ])
                ->renderAsBadges([
                    'en attente' => 'warning',
                    'en cours' => 'info',
                    'livrée' => 'success',
                    'annulée' => 'danger',
                ]),
        ];
    }
}