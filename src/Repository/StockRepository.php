<?php

namespace App\Repository;

use App\Entity\Stock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Stock>
 */
class StockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stock::class);
    }

    public function findByProduct(int $productId): array
    {
        return $this->createQueryBuilder('s')
            ->select('s', 'l')
            ->innerJoin('s.location', 'l')
            ->andWhere('s.product = :productId')
            ->setParameter('productId', $productId)
            ->getQuery()
            ->getResult();
    }

    public function findByLocation(int $locationId): array
    {
        return $this->createQueryBuilder('s')
            ->select('s', 'p')
            ->innerJoin('s.product', 'p')
            ->andWhere('s.location = :locationId')
            ->setParameter('locationId', $locationId)
            ->getQuery()
            ->getResult();
    }
}
