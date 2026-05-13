<?php

namespace App\Repository;

use App\Entity\OperationLine;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OperationLine>
 */
class OperationLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OperationLine::class);
    }

    public function createByProductQueryBuilder(Product $product): QueryBuilder
    {
        return $this->createQueryBuilder('ol')
            ->innerJoin('ol.operation', 'o')
            ->where('ol.product = :product')
            ->orderBy('o.documentDate', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setParameter('product', $product);
    }
}
