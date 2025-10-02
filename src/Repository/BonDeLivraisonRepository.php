<?php
// src/Repository/BonDeLivraisonRepository.php
namespace App\Repository;

use App\Entity\BonDeLivraison;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BonDeLivraison>
 *
 * @method BonDeLivraison|null find($id, $lockMode = null, $lockVersion = null)
 * @method BonDeLivraison|null findOneBy(array $criteria, array $orderBy = null)
 * @method BonDeLivraison[]    findAll()
 * @method BonDeLivraison[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BonDeLivraisonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BonDeLivraison::class);
    }

    // <-- PAS BESOIN DE MÉTHODES PERSONNALISÉES POUR L'INSTANT -->
    // La méthode 'findOneBy' que tu utilises dans ton contrôleur est
    // une méthode magique fournie par ServiceEntityRepository.
    // Tu n'as donc rien de plus à ajouter ici.
}