<?php
// src/Repository/CommandeProduitRepository.php
namespace App\Repository;

use App\Entity\CommandeProduit;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CommandeProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommandeProduit::class);
    }

    /**
     * Trouve les produits les plus vendus sur une période, avec filtre optionnel par utilisateur.
     */
    public function findBestSellingProducts(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        ?User $user = null,
        string $userField = 'commercial',
        int $limit = 5
    ): array {
        $qb = $this->createQueryBuilder('cp')
            ->select('p.nom', 'SUM(cp.quantite) as total_quantity')
            ->join('cp.produit', 'p')
            ->join('cp.commande', 'c')
            ->where('c.dateCommande BETWEEN :start AND :end')
            ->andWhere("c.statut != 'annulée'")
            ->setParameter('start', $start)
            
            // === LA CORRECTION EST ICI ===
            // On passe bien la variable $end et non la chaîne de caractères 'end'
            ->setParameter('end', $end) 
            
            ->groupBy('p.nom') // GroupBy sur p.nom est suffisant si les noms sont uniques
            ->orderBy('total_quantity', 'DESC')
            ->setMaxResults($limit);

        // La logique de filtre par utilisateur est conservée
        if ($user !== null && in_array($userField, ['commercial', 'pao'])) {
            $qb->andWhere(sprintf('c.%s = :user', $userField))
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }
}