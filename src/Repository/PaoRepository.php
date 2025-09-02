<?php

namespace App\Repository;

use App\Entity\Pao;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pao>
 *
 * @method Pao|null find($id, $lockMode = null, $lockVersion = null)
 * @method Pao|null findOneBy(array $criteria, array $orderBy = null)
 * @method Pao[]    findAll()
 * @method Pao[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PaoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pao::class);
    }
}
