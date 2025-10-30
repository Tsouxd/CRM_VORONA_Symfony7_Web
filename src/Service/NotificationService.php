<?php
// src/Service/NotificationService.php
namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Crée une notification pour un ou plusieurs utilisateurs.
     * @param string $message
     * @param User[] $users
     * @param string $type
     */
    public function createForUsers(string $message, array $users, string $type = 'commande'): void
    {
        foreach ($users as $user) {
            $notif = new Notification();
            $notif->setUser($user);
            $notif->setMessage($message);
            $notif->setType($type);
            $notif->setIsRead(false);
            $notif->setCreatedAt(new \DateTime());
            $this->em->persist($notif);
        }
        $this->em->flush();
    }

    public function getUnreadNotifications(User $user, int $limit = 5): array
    {
        return $this->em->getRepository(Notification::class)
            ->findBy(['user' => $user, 'isRead' => false], ['createdAt' => 'DESC'], $limit);
    }

    public function getUnreadCount(User $user): int
    {
        return $this->em->getRepository(Notification::class)
            ->count(['user' => $user, 'isRead' => false]);
    }

    public function markAllAsRead(User $user): void
    {
        $notifications = $this->em->getRepository(Notification::class)
            ->findBy(['user' => $user, 'isRead' => false]);

        foreach ($notifications as $n) {
            $n->setIsRead(true);
        }
        $this->em->flush();
    }
}
