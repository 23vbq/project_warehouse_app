<?php

namespace App\Repository;

use App\Entity\Location;
use App\Traits\SanitizesOrderBy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Location>
 */
class LocationRepository extends ServiceEntityRepository
{
    use SanitizesOrderBy;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Location::class);
    }

    public function findForUniqueValidation(array $criteria): array
    {
        $criteria['isActive'] = true;

        return $this->findBy($criteria);
    }

    public function addActiveFilter(QueryBuilder &$qb, string $alias): QueryBuilder
    {
        return $qb->andWhere("$alias.isActive = :locationIsActive")
            ->setParameter('locationIsActive', true);
    }

    public function createIndexQueryBuilder(array $filters = [], array $orderBy = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l');

        if (!empty($filters['query'])) {
            $qb->andWhere('l.code LIKE :query OR l.name LIKE :query')
                ->setParameter('query', '%'.$filters['query'].'%');
        }
        if (!isset($filters['showInactive']) || !$filters['showInactive']) {
            $this->addActiveFilter($qb, 'l');
        }

        $orderMap = [
            'code' => 'l.code',
            'name' => 'l.name',
            'createdAt' => 'l.createdAt',
            'status' => 'l.isActive',
        ];
        if (!empty($orderBy)) {
            foreach ($orderBy as $field => $direction) {
                $qb->addOrderBy($orderMap[$field] ?? 'l.code', $this->sanitizeDirection($direction));
            }
        }
        $qb->addOrderBy('l.code', 'ASC');

        return $qb;
    }
}
