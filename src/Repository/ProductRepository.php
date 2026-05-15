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

    public function save(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
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
        $qb = $this->createQueryBuilder('p')
            ->select(
                'p as product',
                'SUM(s.quantity) as totalStock'
            )
            ->leftJoin('p.stocks', 's')
            ->groupBy('p.id');

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
            'totalStock' => 'case when totalStock is null then 1 else 0 end asc, totalStock',
            'totalPrice' => 'case when totalStock is null then 1 else 0 end asc, totalStock * p.unitPrice',
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

    public function countAll(): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)');

        $this->addActiveFilter($qb, 'p');

        return (int) $qb->getQuery()
            ->getSingleScalarResult();
    }

    public function searchByQuery(string $query, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p')
            ->where('
                p.name LIKE :query
                OR p.sku LIKE :query
                OR p.ean LIKE :query
            ')
            ->setParameter('query', '%'.$query.'%')
            ->setMaxResults($limit);

        $this->addActiveFilter($qb, 'p');

        return $qb->getQuery()
            ->getResult();
    }

    public function getKpiQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->select(
                'SUM(s.quantity) as totalStock',
                'SUM(s.quantity * p.unitPrice) as totalValue',
                'COUNT(DISTINCT s.location) as locationCount'
            )
            ->leftJoin('p.stocks', 's');
    }

    public function getKpiForProduct(Product $product): array
    {
        return $this->getKpiQueryBuilder()
            ->where('p.id = :productId')
            ->setParameter('productId', $product->getId())
            ->groupBy('p.id')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getGlobalKpi(): array
    {
        $qb = $this->getKpiQueryBuilder()
            ->addSelect('COUNT(DISTINCT p.id) as productCount');

        $this->addActiveFilter($qb, 'p');

        return $qb->getQuery()->getSingleResult() ?? [];
    }
}
