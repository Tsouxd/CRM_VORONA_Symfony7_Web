<?php

namespace App\Repository;

use App\Entity\CommandeProduit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
    public function findBestSellingProducts(\DateTime $start, \DateTime $end, int $limit = 5): array
    {
        return $this->createQueryBuilder('cp')
            ->select('p.nom as product_name, SUM(cp.quantite) as total_quantity')
            ->join('cp.produit', 'p')
            ->join('cp.commande', 'c')
            ->where('c.dateCommande BETWEEN :start AND :end')
            ->andWhere("c.statut != 'annulée'")
            ->setParameter('start', $start)
            // ✅ CORRECTION ICI : Remplacer 'end' par la variable $end
            ->setParameter('end', $end) 
            ->groupBy('p.id', 'p.nom')
            ->orderBy('total_quantity', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}