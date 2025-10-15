<?php

namespace App\Repository;

use App\Entity\Commande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;

class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    /**
     * Calcule les statistiques mensuelles.
     * Si $userId est null → global (admin).
     * Sinon → statistiques filtrées par commercial (pao).
     */
    public function getMonthlyStatistics(?int $userId = null): array
    {
        $qbFrais = $this->createQueryBuilder('c')
            ->select('SUBSTRING(c.dateCommande, 1, 7) as period, COUNT(DISTINCT c.id) as orderCount, SUM(c.fraisLivraison) as totalFrais')
            ->where('c.dateCommande >= :date')
            ->setParameter('date', new \DateTime('-12 months'))
            ->andWhere("c.statut != 'annulée'");

        if ($userId !== null) {
            // ⚠️ c.pao est une association → on filtre par son id
            $qbFrais->andWhere('IDENTITY(c.pao) = :userId')->setParameter('userId', $userId);
        }

        $fraisParMois = $qbFrais->groupBy('period')->orderBy('period', 'ASC')->getQuery()->getResult();

        $qbProduits = $this->createQueryBuilder('c')
            ->select('SUBSTRING(c.dateCommande, 1, 7) as period, SUM(cp.quantite * p.prix) as totalProduits')
            ->join('c.commandeProduits', 'cp')
            ->join('cp.produit', 'p')
            ->where('c.dateCommande >= :date')
            ->setParameter('date', new \DateTime('-12 months'))
            ->andWhere("c.statut != 'annulée'");

        if ($userId !== null) {
            $qbProduits->andWhere('IDENTITY(c.pao) = :userId')->setParameter('userId', $userId);
        }

        $produitsParMois = $qbProduits->groupBy('period')->orderBy('period', 'ASC')->getQuery()->getResult();

        $results = [];
        foreach ($fraisParMois as $frais) {
            $results[$frais['period']] = [
                'period' => $frais['period'],
                'orderCount' => (int) $frais['orderCount'],
                'totalAmount' => (float) ($frais['totalFrais'] ?? 0),
            ];
        }

        foreach ($produitsParMois as $produit) {
            if (!isset($results[$produit['period']])) {
                $results[$produit['period']] = [
                    'period' => $produit['period'],
                    'orderCount' => 0,
                    'totalAmount' => 0.0,
                ];
            }
            $results[$produit['period']]['totalAmount'] += (float) ($produit['totalProduits'] ?? 0);
        }

        ksort($results); // sécurité d'ordre
        return array_values($results);
    }

    /**
     * Calcule les statistiques annuelles.
     * Même principe que mensuelles avec $userId.
     */
    public function getYearlyStatistics(?int $userId = null): array
    {
        $qbFrais = $this->createQueryBuilder('c')
            ->select('SUBSTRING(c.dateCommande, 1, 4) as period, COUNT(DISTINCT c.id) as orderCount, SUM(c.fraisLivraison) as totalFrais')
            ->andWhere("c.statut != 'annulée'");

        if ($userId !== null) {
            $qbFrais->andWhere('IDENTITY(c.pao) = :userId')->setParameter('userId', $userId);
        }

        $fraisParAn = $qbFrais->groupBy('period')->orderBy('period', 'DESC')->getQuery()->getResult();

        $qbProduits = $this->createQueryBuilder('c')
            ->select('SUBSTRING(c.dateCommande, 1, 4) as period, SUM(cp.quantite * p.prix) as totalProduits')
            ->join('c.commandeProduits', 'cp')->join('cp.produit', 'p')
            ->andWhere("c.statut != 'annulée'");

        if ($userId !== null) {
            $qbProduits->andWhere('IDENTITY(c.pao) = :userId')->setParameter('userId', $userId);
        }

        $produitsParAn = $qbProduits->groupBy('period')->orderBy('period', 'DESC')->getQuery()->getResult();

        $results = [];
        foreach ($fraisParAn as $frais) {
            $results[$frais['period']] = [
                'period' => $frais['period'],
                'orderCount' => (int) $frais['orderCount'],
                'totalAmount' => (float) ($frais['totalFrais'] ?? 0),
            ];
        }

        foreach ($produitsParAn as $produit) {
            if (!isset($results[$produit['period']])) {
                $results[$produit['period']] = [
                    'period' => $produit['period'],
                    'orderCount' => 0,
                    'totalAmount' => 0.0,
                ];
            }
            $results[$produit['period']]['totalAmount'] += (float) ($produit['totalProduits'] ?? 0);
        }

        return array_values($results);
    }

    public function findTotalSalesBetweenDates(\DateTime $start, \DateTime $end, ?User $user = null, string $userField = 'commercial'): float
    {
        // --- PARTIE 1 : Calcul du total des produits ---
        $qbProduits = $this->getEntityManager()->createQueryBuilder()
            ->select('COALESCE(SUM(cp.quantite * p.prix), 0)')
            ->from('App\Entity\CommandeProduit', 'cp')
            ->join('cp.commande', 'c') // On joint sur Commande pour pouvoir filtrer
            ->join('cp.produit', 'p')
            ->where('c.dateCommande BETWEEN :start AND :end')
            ->andWhere("c.statut != 'annulée'")
            ->setParameter('start', $start)->setParameter('end', $end);

        // On applique le filtre utilisateur si nécessaire
        if ($user !== null && in_array($userField, ['commercial', 'pao'])) {
            $qbProduits->andWhere(sprintf('c.%s = :user', $userField))->setParameter('user', $user);
        }
        $totalProduits = (float) $qbProduits->getQuery()->getSingleScalarResult();


        // --- PARTIE 2 : Calcul du total des frais de livraison ---
        $qbFrais = $this->createQueryBuilder('c')
            ->select('COALESCE(SUM(c.fraisLivraison), 0)')
            ->where('c.dateCommande BETWEEN :start AND :end')
            ->andWhere("c.statut != 'annulée'")
            ->setParameter('start', $start)->setParameter('end', $end);

        // On applique le filtre utilisateur ici aussi
        if ($user !== null && in_array($userField, ['commercial', 'pao'])) {
            $qbFrais->andWhere(sprintf('c.%s = :user', $userField))->setParameter('user', $user);
        }
        $totalFrais = (float) $qbFrais->getQuery()->getSingleScalarResult();

        // On retourne la somme des deux, ce qui équivaut à totalAvecFrais
        return $totalProduits + $totalFrais;
    }
    
    /**
     * NOUVELLE MÉTHODE pour compter les stats PAO.
     */
    public function countCommandsByPaoStatusForUser(User $paoUser): array
    {
        $results = $this->createQueryBuilder('c')
            ->select('c.statutPao, COUNT(c.id) as count')
            ->where('c.pao = :user')
            ->setParameter('user', $paoUser)
            ->groupBy('c.statutPao')
            ->getQuery()
            ->getResult();
            
        return array_column($results, 'count', 'statutPao');
    }

    public function findAvailableYears(?int $userId = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('DISTINCT SUBSTRING(c.dateCommande, 1, 4) as year')
            ->orderBy('year', 'DESC');

        if ($userId !== null) {
            $qb->andWhere('IDENTITY(c.pao) = :userId')->setParameter('userId', $userId);
        }

        return $qb->getQuery()->getResult();
    }

    public function findAvailableMonths(?int $userId = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('DISTINCT SUBSTRING(c.dateCommande, 1, 7) as month')
            ->orderBy('month', 'DESC');

        if ($userId !== null) {
            $qb->andWhere('IDENTITY(c.pao) = :userId')->setParameter('userId', $userId);
        }

        return $qb->getQuery()->getResult();
    }

    private function getProductionStatuses(): array
    {
        return ['en cours', 'payée', 'partiellement payée'];
    }

    public function countForProduction(?int $userId = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.statut IN (:statuses)')
            ->setParameter('statuses', $this->getProductionStatuses());

        if ($userId !== null) {
            $qb->andWhere('IDENTITY(c.pao) = :userId')->setParameter('userId', $userId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function sumItemsForProduction(?int $userId = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COALESCE(SUM(cp.quantite), 0)')
            ->join('c.commandeProduits', 'cp')
            ->where('c.statut IN (:statuses)')
            ->setParameter('statuses', $this->getProductionStatuses());

        if ($userId !== null) {
            $qb->andWhere('IDENTITY(c.pao) = :userId')->setParameter('userId', $userId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function getProductionQueue(int $limit = 10, ?int $userId = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('p.nom as productName, SUM(cp.quantite) as totalQuantity')
            ->join('c.commandeProduits', 'cp')
            ->join('cp.produit', 'p')
            ->where('c.statut IN (:statuses)')
            ->setParameter('statuses', $this->getProductionStatuses())
            ->groupBy('p.id', 'p.nom')
            ->orderBy('totalQuantity', 'DESC')
            ->setMaxResults($limit);

        if ($userId !== null) {
            $qb->andWhere('IDENTITY(c.pao) = :userId')->setParameter('userId', $userId);
        }

        return $qb->getQuery()->getResult();
    }
}