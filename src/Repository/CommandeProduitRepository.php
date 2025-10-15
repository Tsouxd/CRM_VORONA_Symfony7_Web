<?php

namespace App\Repository;

use App\Entity\CommandeProduit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;
/**
 * @extends ServiceEntityRepository<CommandeProduit>
 */
class CommandeProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommandeProduit::class);
    }

    /**
     * Trouve les produits les plus vendus (en quantité) sur une période donnée.
     */
    public function findBestSellingProducts(\DateTime $start, \DateTime $end, ?User $user = null, string $userField = 'commercial', int $limit = 5): array
    {
        $qb = $this->createQueryBuilder('cp')
            ->select('p.nom', 'SUM(cp.quantite) as total_quantity')
            ->join('cp.produit', 'p')
            ->join('cp.commande', 'c')
            ->where('c.dateCommande BETWEEN :start AND :end')
            ->andWhere("c.statut != 'annulée'")
            ->setParameter('start', $start)
            ->setParameter('end', 'end')
            ->groupBy('p.nom')
            ->orderBy('total_quantity', 'DESC')
            ->setMaxResults($limit);

        if ($user !== null && in_array($userField, ['commercial', 'pao'])) {
            $qb->andWhere(sprintf('c.%s = :user', $userField))
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }
}