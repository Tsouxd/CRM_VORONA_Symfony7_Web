<?php
// src/EventSubscriber/ProductionWorkflowSubscriber.php
namespace App\EventSubscriber;

use App\Entity\Commande;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductionWorkflowSubscriber implements EventSubscriberInterface
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityUpdatedEvent::class => ['onBeforeEntityUpdated'],
        ];
    }

    public function onBeforeEntityUpdated(BeforeEntityUpdatedEvent $event)
    {
        $entity = $event->getEntityInstance();
        if (!($entity instanceof Commande)) return;

        $uow = $this->entityManager->getUnitOfWork();
        $originalData = $uow->getOriginalEntityData($entity);

        // On définit les 3 cas possibles concernant la case à cocher
        $isJustChecked = ($entity->isProductionTermineeOk() === true && $originalData['productionTermineeOk'] === false);
        $isJustUnchecked = ($entity->isProductionTermineeOk() === false && $originalData['productionTermineeOk'] === true);
        $wasAlreadyChecked = ($entity->isProductionTermineeOk() === true && $originalData['productionTermineeOk'] === true);

        // --- CAS N°1 : L'utilisateur vient de COCHER la case ---
        // C'est ton code qui marche, on le garde.
        if ($isJustChecked) {
            $entity->setStatutProduction(Commande::STATUT_PRODUCTION_POUR_LIVRAISON);
            $entity->setStatutLivraison(Commande::STATUT_LIVRAISON_ATTENTE);
            return; // Le travail est fait.
        }

        // --- CAS N°2 : L'utilisateur vient de DÉCOCHER la case ---
        // On ajoute cette logique pour revenir en arrière proprement.
        if ($isJustUnchecked) {
            $entity->setStatutProduction(Commande::STATUT_PRODUCTION_EN_COURS);
            $entity->setStatutLivraison(null); // On nettoie les données de livraison
            $entity->setNomLivreur(null);
            $entity->setDateDeLivraison(null);
            return; // Le travail est fait.
        }

        // --- CAS N°3 : La case n'a PAS changé et était déjà cochée ---
        // C'est cette partie qui corrige ton bug. Elle garantit que si la production
        // est terminée, son statut ne peut plus revenir en arrière par erreur.
        if ($wasAlreadyChecked) {
            // Si quelque chose d'autre a essayé de modifier le statut de production,
            // on le force à rester sur la bonne valeur.
            $entity->setStatutProduction(Commande::STATUT_PRODUCTION_POUR_LIVRAISON);
        }
    }
}