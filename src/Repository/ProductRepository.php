<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function addActiveFilter(QueryBuilder &$qb, string $alias): QueryBuilder
    {
        return $qb->andWhere("$alias.isActive = :productIsActive")
            ->setParameter('productIsActive', true);
    }

    public function createIndexQueryBuilder(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.name', 'ASC');

        if (!empty($filters['type'])) {
            $qb->andWhere('p.type = :type')
                ->setParameter('type', $filters['type']);
        }
        if (!empty($filters['query'])) {
            $qb->andWhere('
                p.name LIKE :query
                OR p.sku LIKE :query
                OR p.ean LIKE :query
            ')
                ->setParameter('query', '%'.$filters['query'].'%');
        }

        $this->addActiveFilter($qb, 'p');

        return $qb;
    }
}
