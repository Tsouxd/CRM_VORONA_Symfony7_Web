<?php
// src/EventSubscriber/PaoWorkflowSubscriber.php
namespace App\EventSubscriber;

use App\Entity\Commande;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaoWorkflowSubscriber implements EventSubscriberInterface
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
        if (!($entity instanceof Commande)) {
            return;
        }

        $uow = $this->entityManager->getUnitOfWork();
        $originalData = $uow->getOriginalEntityData($entity);
        
        // --- Règle 0 : AUTOMATISATION CÔTÉ PAO ---
        // On détecte si une case de modification vient d'être cochée (passant de false à true)
        $modif1JustChecked = ($entity->isPaoModif1Ok() && !$originalData['paoModif1Ok']);
        $modif2JustChecked = ($entity->isPaoModif2Ok() && !$originalData['paoModif2Ok']);
        $modif3JustChecked = ($entity->isPaoModif3Ok() && !$originalData['paoModif3Ok']);

        if ($modif1JustChecked || $modif2JustChecked || $modif3JustChecked) {
            // Le PAO a fini son travail, on automatise la suite !
            $entity->setPaoBatValidation(Commande::BAT_EN_ATTENTE); // On resoumet pour validation
            $entity->setStatutPao(Commande::STATUT_PAO_EN_COURS);     // Le statut redevient "en cours" de validation
            $entity->setPaoMotifModification(null);                  // On nettoie le champ de saisie du motif
            
            // Pas besoin de continuer, cette action a priorité.
            return; 
        }

        // --- Règle 1 : VALIDATION FORCÉE APRÈS M3 ---
        if ($entity->isPaoModif3Ok() && $entity->getPaoBatValidation() === Commande::BAT_MODIFICATION) {
            $entity->setPaoBatValidation(Commande::BAT_PRODUCTION);
            $entity->setStatutPao(Commande::STATUT_PAO_FAIT);
            $motifActuel = $entity->getPaoMotifModification();
            $entity->setPaoMotifM3("VALIDATION FORCÉE. Dernier motif: " . $motifActuel);
            $entity->setPaoMotifModification(null);
            return;
        }

        // --- Règle 2 : Le commercial demande une modification ---
        if ($entity->getPaoBatValidation() === Commande::BAT_MODIFICATION) {
            $entity->setStatutPao(Commande::STATUT_PAO_MODIFICATION);
            $motifSaisi = $entity->getPaoMotifModification();
            
            if (!$entity->isPaoModif1Ok()) {
                $entity->setPaoMotifM1($motifSaisi);
            } elseif (!$entity->isPaoModif2Ok()) {
                $entity->setPaoMotifM2($motifSaisi);
            } elseif (!$entity->isPaoModif3Ok()) {
                $entity->setPaoMotifM3($motifSaisi);
            }
        }

        // --- Règle 3 : Le commercial valide pour production ---
        if ($entity->getPaoBatValidation() === Commande::BAT_PRODUCTION) {
            $entity->setStatutPao(Commande::STATUT_PAO_FAIT);
            $entity->setPaoMotifModification(null);
        }
    }
}