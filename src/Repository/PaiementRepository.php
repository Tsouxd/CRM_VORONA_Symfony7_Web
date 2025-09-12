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

    /**
     * Calcule le total théorique des paiements non clôturés,
     * groupé par la méthode de paiement (Espèces, Mobile Money, etc.).
     */
    public function findTotalsToClose(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.referencePaiement, SUM(p.montant) as totalTheorique')
            ->join('p.commande', 'c')
            ->where('p.arretDeCaisse IS NULL')
            ->andWhere("p.statut = 'effectué' OR p.statut = 'payée'")
            ->andWhere('p.referencePaiement IS NOT NULL')
            ->groupBy('p.referencePaiement')
            ->getQuery()
            ->getResult();
    }
}
