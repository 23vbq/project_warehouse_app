<?php

namespace App\Repository;

use App\Entity\Product;
use App\Enum\ProductType;
use App\Traits\SanitizesOrderBy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    use SanitizesOrderBy;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findForUniqueValidation(array $criteria): array
    {
        $criteria['isActive'] = true;

        return $this->findBy($criteria);
    }

    public function addActiveFilter(QueryBuilder &$qb, string $alias): QueryBuilder
    {
        return $qb->andWhere("$alias.isActive = :productIsActive")
            ->setParameter('productIsActive', true);
    }

    public function createIndexQueryBuilder(array $filters = [], array $orderBy = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p');

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
        if (!isset($filters['showInactive']) || !$filters['showInactive']) {
            $this->addActiveFilter($qb, 'p');
        }

        $orderMap = [
            'name' => 'p.name',
            'type' => 'p.type',
            'createdAt' => 'p.createdAt',
            'sku' => 'p.sku',
            'unitPrice' => 'p.unitPrice',
            'status' => 'p.isActive',
        ];
        if (!empty($orderBy)) {
            foreach ($orderBy as $field => $direction) {
                $qb->addOrderBy($orderMap[$field] ?? 'p.name', $this->sanitizeDirection($direction));
            }
        }
        $qb->addOrderBy('p.name', 'ASC');

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
