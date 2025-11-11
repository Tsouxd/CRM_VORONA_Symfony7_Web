<?php

namespace App\Repository;

use App\Entity\Commande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;
use App\Entity\Paiement;

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

    /**
     * Récupère un résumé des performances pour un commercial donné.
     */
    public function getCommercialSummary(User $commercial, ?\DateTime $searchDate = null): array
    {
        // Total de commandes
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.commercial = :commercial')
            ->setParameter('commercial', $commercial);

        if ($searchDate) {
            $startOfDay = (clone $searchDate)->setTime(0, 0, 0);
            $endOfDay = (clone $searchDate)->setTime(23, 59, 59);

            $qb->andWhere('c.dateCommande BETWEEN :start AND :end')
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay);
        }

        $totalCommands = $qb->getQuery()->getSingleScalarResult();

        // Commandes les plus anciennes
        $qb2 = $this->createQueryBuilder('c')
            ->where('c.commercial = :commercial')
            ->andWhere("c.statut NOT IN ('livrée', 'annulée')")
            ->setParameter('commercial', $commercial)
            ->orderBy('c.dateCommande', 'ASC')
            ->setMaxResults(5);

        if ($searchDate) {
            $startOfDay = (clone $searchDate)->setTime(0, 0, 0);
            $endOfDay = (clone $searchDate)->setTime(23, 59, 59);

            $qb2->andWhere('c.dateCommande BETWEEN :start AND :end')
                ->setParameter('start', $startOfDay)
                ->setParameter('end', $endOfDay);
        }

        $oldestCommands = $qb2->getQuery()->getResult();

        return [
            'totalCommands' => (int) $totalCommands,
            'oldestCommands' => $oldestCommands,
        ];
    }

    /**
     * Récupère les commandes d'un PAO groupées par statut.
     */
    public function findCommandsByPaoGroupedByStatus(User $paoUser): array
    {
        $statuses = [
            Commande::STATUT_PAO_ATTENTE,
            Commande::STATUT_PAO_EN_COURS,
            Commande::STATUT_PAO_MODIFICATION,
            Commande::STATUT_PAO_FAIT,
        ];

        $results = [];
        foreach ($statuses as $status) {
            $results[$status] = $this->createQueryBuilder('c')
                ->andWhere('c.pao = :pao')
                ->andWhere('c.statutPao = :status')
                ->setParameter('pao', $paoUser)
                ->setParameter('status', $status)
                ->orderBy('c.dateCommande', 'DESC')
                ->getQuery()
                ->getResult();
        }

        return $results;
    }

    /**
     * Compte les commandes par statut de production pour un utilisateur donné.
     *
     * @param User $user L'utilisateur avec le rôle ROLE_PRODUCTION.
     * @return array Un tableau associatif [statut => nombre].
     */
    public function countCommandsByProductionStatusForUser(User $user): array
    {
        $results = $this->createQueryBuilder('c')
            ->select('c.statutProduction as statut, COUNT(c.id) as total')
            ->where('c.production = :user')
            ->setParameter('user', $user)
            ->groupBy('c.statutProduction')
            ->getQuery()
            ->getResult();

        // Met en forme le résultat pour un accès facile [statut => total]
        return array_column($results, 'total', 'statut');
    }

    /**
     * Trouve les commandes de production pour un utilisateur, groupées par statut.
     *
     * @param User $user L'utilisateur avec le rôle ROLE_PRODUCTION.
     * @return array Un tableau associatif [statut => array<Commande>].
     */
    public function findCommandsByProductionGroupedByStatus(User $user): array
    {
        $results = $this->createQueryBuilder('c')
            ->where('c.production = :user')
            ->setParameter('user', $user)
            // Trier pour voir les plus urgentes ou les plus anciennes en premier
            ->orderBy('c.priorite', 'DESC')
            ->addOrderBy('c.dateCommande', 'ASC')
            ->getQuery()
            ->getResult();

        $groupedCommands = [];
        foreach ($results as $commande) {
            $statut = $commande->getStatutProduction();
            if (!isset($groupedCommands[$statut])) {
                $groupedCommands[$statut] = [];
            }
            $groupedCommands[$statut][] = $commande;
        }

        return $groupedCommands;
    }

    public function findForComptableDashboard(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('c')
            // Jointures nécessaires
            ->leftJoin('c.client', 'client')
            ->leftJoin('c.commandeProduits', 'cp')
            ->leftJoin('cp.produit', 'prod')
            ->leftJoin('c.paiements', 'p', 'WITH', 'p.statut = :statutEffectue')

            // On sélectionne l'entité principale d'abord
            ->select('c')

            // On ajoute des parenthèses autour du calcul complet pour lever l'ambiguïté
            ->addSelect('(COALESCE(SUM(cp.quantite * prod.prix), 0) + c.fraisLivraison) as totalCommande')
            // =========================================================================
            
            ->addSelect('COALESCE(SUM(p.montant), 0) as montantPaye')
            
            ->where('c.statut != :statutAnnulee')
            ->groupBy('c.id, client.id')
            ->orderBy('c.dateCommande', 'DESC')
            
            ->setParameter('statutEffectue', Paiement::STATUT_EFFECTUE)
            ->setParameter('statutAnnulee', 'annulée');

        
        // Les filtres ne changent pas
        if (!empty($filters['client'])) {
            $qb->andWhere('client.id = :clientId')->setParameter('clientId', $filters['client']);
        }
        if (!empty($filters['date_debut'])) {
            $qb->andWhere('c.dateCommande >= :dateDebut')->setParameter('dateDebut', $filters['date_debut']);
        }
        if (!empty($filters['date_fin'])) {
             $dateFin = new \DateTime($filters['date_fin']);
             $dateFin->modify('+1 day');
             $qb->andWhere('c.dateCommande < :dateFin')->setParameter('dateFin', $dateFin);
        }
        if (!empty($filters['statut'])) {
            $qb->andWhere('c.statutComptable = :statut')->setParameter('statut', $filters['statut']);
        }
        if (!empty($filters['non_solde'])) {
            // ON APPLIQUE LA MÊME CORRECTION DANS LA CLAUSE HAVING
            $qb->having('(COALESCE(SUM(cp.quantite * prod.prix), 0) + c.fraisLivraison) > COALESCE(SUM(p.montant), 0)');
        }

        $result = $qb->getQuery()->getResult();

        // Le contrôleur s'attend à un format spécifique, nous allons le garantir
        return array_map(function($row) {
            return [
                0 => $row[0], // L'objet Commande
                'commande' => $row[0], // Pour la lisibilité (optionnel)
                'totalCommande' => $row['totalCommande'],
                'montantPaye' => $row['montantPaye'],
            ];
        }, $result);
    }

    public function findCommandesEchuesNonPayees(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.dateEcheance IS NOT NULL') // Il faut une date d'échéance
            ->andWhere('c.dateEcheance < :aujourdhui') // Dont la date est passée
            // Qui ne sont ni PAYE, ni RECOUVREMENT
            ->andWhere('c.statutComptable IN (:statuts)')
            ->setParameter('aujourdhui', new \DateTimeImmutable('today'))
            ->setParameter('statuts', [Commande::STATUT_COMPTABLE_ATTENTE, Commande::STATUT_COMPTABLE_PARTIEL])
            ->getQuery()
            ->getResult();
    }
}