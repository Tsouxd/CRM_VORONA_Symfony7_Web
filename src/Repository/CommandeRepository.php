<?php

namespace App\Repository;

use App\Entity\Commande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    public function findCommandesDes12DerniersMois(): array
    {
        $date12MonthsAgo = new \DateTimeImmutable('-12 months');

        return $this->createQueryBuilder('c')
            ->leftJoin('c.commandeProduits', 'cp')
            ->leftJoin('cp.produit', 'p')
            ->addSelect('cp', 'p')
            ->where('c.dateCommande >= :date')
            ->setParameter('date', $date12MonthsAgo)
            ->orderBy('c.dateCommande', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getMonthlyStatistics(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT 
                DATE_FORMAT(c.date_commande, '%Y-%m') AS period,
                COUNT(c.id) AS orderCount,
                SUM(cp.quantite * p.prix) / 100 AS totalAmount
            FROM commande c
            JOIN commande_produit cp ON c.id = cp.commande_id
            JOIN produit p ON cp.produit_id = p.id
            WHERE c.date_commande >= :startDate
            GROUP BY period
            ORDER BY period ASC
        ";

        $stmt = $conn->prepare($sql);
        $startDate = (new \DateTime('-11 months'))->format('Y-m-01');
        $result = $stmt->executeQuery(['startDate' => $startDate]);

        return $result->fetchAllAssociative();
    }

    public function getYearlyStatistics(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT 
                DATE_FORMAT(c.date_commande, '%Y') AS period,
                COUNT(c.id) AS orderCount,
                SUM(cp.quantite * p.prix) / 100 AS totalAmount
            FROM commande c
            JOIN commande_produit cp ON c.id = cp.commande_id
            JOIN produit p ON cp.produit_id = p.id
            GROUP BY period
            ORDER BY period ASC
        ";

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

//    /**
//     * @return Commande[] Returns an array of Commande objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Commande
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
