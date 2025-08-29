<?php

namespace App\Repository;

use App\Entity\Commande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    /**
     * Calcule les statistiques mensuelles.
     * Cette méthode est maintenant correcte et gère bien les frais de livraison.
     */
    public function getMonthlyStatistics(): array
    {
        // 1. Calculer les frais de livraison et le nombre de commandes par mois
        $fraisParMois = $this->createQueryBuilder('c')
           ->select('SUBSTRING(c.dateCommande, 1, 7) as period, COUNT(DISTINCT c.id) as orderCount, SUM(c.fraisLivraison) as totalFrais')
           ->where('c.dateCommande >= :date')->setParameter('date', new \DateTime('-12 months'))
           ->andWhere("c.statut != 'annulée'")
           ->groupBy('period')->orderBy('period', 'ASC')
           ->getQuery()->getResult();

        // 2. Calculer le total des produits par mois
        $produitsParMois = $this->createQueryBuilder('c')
            ->select('SUBSTRING(c.dateCommande, 1, 7) as period, SUM(cp.quantite * p.prix) as totalProduits')
            ->join('c.commandeProduits', 'cp')->join('cp.produit', 'p')
            ->where('c.dateCommande >= :date')->setParameter('date', new \DateTime('-12 months'))
            ->andWhere("c.statut != 'annulée'")
            ->groupBy('period')->orderBy('period', 'ASC')
            ->getQuery()->getResult();

        // 3. Fusionner les deux résultats en PHP pour un total exact
        $results = [];
        foreach ($fraisParMois as $frais) {
            $results[$frais['period']] = [
                'period' => $frais['period'],
                'orderCount' => $frais['orderCount'],
                'totalAmount' => (float) $frais['totalFrais']
            ];
        }
        foreach ($produitsParMois as $produit) {
            // S'il existe des ventes de produits pour ce mois, on les ajoute
            if (isset($results[$produit['period']])) {
                $results[$produit['period']]['totalAmount'] += (float) $produit['totalProduits'];
            }
        }
        return array_values($results);
    }
    
    /**
     * Calcule les statistiques annuelles.
     * Cette méthode est maintenant correcte.
     */
    public function getYearlyStatistics(): array
    {
        $fraisParAn = $this->createQueryBuilder('c')
           ->select('SUBSTRING(c.dateCommande, 1, 4) as period, COUNT(DISTINCT c.id) as orderCount, SUM(c.fraisLivraison) as totalFrais')
           ->andWhere("c.statut != 'annulée'")
           ->groupBy('period')->orderBy('period', 'DESC')
           ->getQuery()->getResult();

        $produitsParAn = $this->createQueryBuilder('c')
            ->select('SUBSTRING(c.dateCommande, 1, 4) as period, SUM(cp.quantite * p.prix) as totalProduits')
            ->join('c.commandeProduits', 'cp')->join('cp.produit', 'p')
            ->andWhere("c.statut != 'annulée'")
            ->groupBy('period')->orderBy('period', 'DESC')
            ->getQuery()->getResult();

        $results = [];
        foreach ($fraisParAn as $frais) {
            $results[$frais['period']] = [
                'period' => $frais['period'],
                'orderCount' => $frais['orderCount'],
                'totalAmount' => (float) $frais['totalFrais']
            ];
        }
        foreach ($produitsParAn as $produit) {
            if (isset($results[$produit['period']])) {
                $results[$produit['period']]['totalAmount'] += (float) $produit['totalProduits'];
            }
        }
        return array_values($results);
    }

    /**
     * Calcule le total des ventes pour une période donnée.
     * Cette méthode est déjà correcte.
     */
    public function findTotalSalesBetweenDates(\DateTime $start, \DateTime $end): float
    {
        $totalProduits = (float) $this->getEntityManager()->createQueryBuilder()
            ->select('SUM(cp.quantite * p.prix)')
            ->from('App\Entity\CommandeProduit', 'cp')
            ->join('cp.commande', 'c_sub')->join('cp.produit', 'p')
            ->where('c_sub.dateCommande BETWEEN :start AND :end')->andWhere("c_sub.statut != 'annulée'")
            ->getQuery()->setParameter('start', $start)->setParameter('end', $end)->getSingleScalarResult();

        $totalFrais = (float) $this->createQueryBuilder('c')
            ->select('SUM(c.fraisLivraison)')
            ->where('c.dateCommande BETWEEN :start AND :end')->andWhere("c.statut != 'annulée'")
            ->setParameter('start', $start)->setParameter('end', $end)->getQuery()->getSingleScalarResult();

        return $totalProduits + $totalFrais;
    }

    /**
     * Retourne les années disponibles (inchangé).
     */
    public function findAvailableYears(): array
    {
        return $this->createQueryBuilder('c')
            ->select('DISTINCT SUBSTRING(c.dateCommande, 1, 4) as year')
            ->orderBy('year', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les mois disponibles (inchangé).
     */
    public function findAvailableMonths(): array
    {
        return $this->createQueryBuilder('c')
            ->select('DISTINCT SUBSTRING(c.dateCommande, 1, 7) as month')
            ->orderBy('month', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return string[]
     */
    private function getProductionStatuses(): array
    {
        // Statuts qui signifient qu'une commande doit être préparée
        return ['en cours', 'payée', 'partiellement payée'];
    }

    /**
     * Compte le nombre de commandes en attente de production.
     */
    public function countForProduction(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.statut IN (:statuses)')
            ->setParameter('statuses', $this->getProductionStatuses())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Calcule le nombre total d'articles à préparer pour toutes les commandes.
     */
    public function sumItemsForProduction(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('SUM(cp.quantite)')
            ->join('c.commandeProduits', 'cp')
            ->where('c.statut IN (:statuses)')
            ->setParameter('statuses', $this->getProductionStatuses())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Crée une "liste de courses" de production : le total de chaque produit à préparer.
     */
    public function getProductionQueue(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->select('p.nom as productName, SUM(cp.quantite) as totalQuantity')
            ->join('c.commandeProduits', 'cp')
            ->join('cp.produit', 'p')
            ->where('c.statut IN (:statuses)')
            ->setParameter('statuses', $this->getProductionStatuses())
            ->groupBy('p.id', 'p.nom')
            ->orderBy('totalQuantity', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}