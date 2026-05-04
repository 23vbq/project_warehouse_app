<?php

namespace App\Repository;

use App\Entity\Product;
use App\Enum\ProductType;
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

    public function countTypesAndTotal(): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select(
                'COUNT(p.id) as total,
                SUM(CASE WHEN p.type = :finished THEN 1 ELSE 0 END) as finishedCount,
                SUM(CASE WHEN p.type = :semi THEN 1 ELSE 0 END) as semiCount,
                SUM(CASE WHEN p.type = :raw THEN 1 ELSE 0 END) as rawCount,
                SUM(CASE WHEN p.type = :consumables THEN 1 ELSE 0 END) as consumablesCount
            ')
            ->setParameter('finished', ProductType::FINISHED->value)
            ->setParameter('semi', ProductType::SEMI->value)
            ->setParameter('raw', ProductType::RAW->value)
            ->setParameter('consumables', ProductType::CONSUMABLES->value);

        $this->addActiveFilter($qb, 'p');

        return $qb->getQuery()
            ->getSingleResult();
    }
}
