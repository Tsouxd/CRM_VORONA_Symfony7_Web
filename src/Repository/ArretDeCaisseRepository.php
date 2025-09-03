<?php
// src/Repository/ArretDeCaisseRepository.php

namespace App\Repository;

use App\Entity\ArretDeCaisse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArretDeCaisse>
 */
class ArretDeCaisseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArretDeCaisse::class);
    }
}