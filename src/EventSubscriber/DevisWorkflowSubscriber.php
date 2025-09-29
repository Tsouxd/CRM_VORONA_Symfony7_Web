<?php
// src/EventSubscriber/DevisWorkflowSubscriber.php
namespace App\EventSubscriber;

use App\Entity\Devis;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DevisWorkflowSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityUpdatedEvent::class => ['onBeforeEntityUpdated'],
        ];
    }

    public function onBeforeEntityUpdated(BeforeEntityUpdatedEvent $event): void
    {
        $devis = $event->getEntityInstance();

        if (!($devis instanceof Devis)) {
            return;
        }

        // --- Statuts manuellement choisis par l'utilisateur ---
        $statutManuel = in_array(
            $devis->getStatut(),
            [Devis::STATUT_RELANCE, Devis::STATUT_PERDU],
            true
        );

        if ($devis->isBatOk()) {
            // ⚡ Si BAT OK coché, toujours passer en BAT/Production
            $devis->setStatut(Devis::STATUT_BAT_PRODUCTION);
        } else {
            // BAT OK décoché
            if (!$statutManuel && $devis->getStatut() === Devis::STATUT_BAT_PRODUCTION) {
                // Si le statut était BAT/Production et qu'aucun choix manuel Relance/Perdu → remettre Envoyé
                $devis->setStatut(Devis::STATUT_ENVOYE);
            }
            // Sinon : on ne touche pas si Relance/Perdu ou autre statut
        }
    }
}