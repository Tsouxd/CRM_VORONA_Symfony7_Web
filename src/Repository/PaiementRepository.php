<?php

namespace App\Repository;

use App\Entity\Paiement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Paiement>
 */
class PaiementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Paiement::class);
    }

//    /**
//     * @return Paiement[] Returns an array of Paiement objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Paiement
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    /**
     * Calcule le total théorique des paiements non clôturés, groupé par la méthode
     * de paiement définie sur la commande parente via 'referencePaiement'.
     */
    public function findTotalsToClose(): array
    {
        return $this->createQueryBuilder('p')
            // ✅ On sélectionne directement 'referencePaiement', sans alias
            ->select('c.referencePaiement, SUM(p.montant) as totalTheorique')
            ->join('p.commande', 'c')
            ->where('p.arretDeCaisse IS NULL')
            ->andWhere("p.statut = 'effectué' OR p.statut = 'payée'")
            ->andWhere('c.referencePaiement IS NOT NULL')
            // ✅ On groupe par le vrai nom du champ
            ->groupBy('c.referencePaiement')
            ->getQuery()
            ->getResult();
    }
}
