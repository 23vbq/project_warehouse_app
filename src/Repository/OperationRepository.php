<?php

namespace App\Repository;

use App\Entity\Operation;
use App\Enum\OperationStatus;
use App\Traits\SanitizesOrderBy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Operation>
 */
class OperationRepository extends ServiceEntityRepository
{
    use SanitizesOrderBy;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Operation::class);
    }

    public function save(Operation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Operation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function createIndexQueryBuilder(array $filters = [], array $orderBy = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.createdBy', 'u');

        if (!empty($filters['type'])) {
            $qb->andWhere('o INSTANCE OF :type')
                ->setParameter('type', $filters['type']);
        }

        if (!empty($filters['query'])) {
            $qb->andWhere('o.fullNumber LIKE :query')
                ->setParameter('query', '%'.$filters['query'].'%');
        }

        $orderMap = [
            'fullNumber' => 'o.fullNumber',
            'documentDate' => 'o.documentDate',
            'createdAt' => 'o.createdAt',
            'status' => 'o.status',
        ];

        if (!empty($orderBy)) {
            foreach ($orderBy as $field => $direction) {
                $qb->addOrderBy($orderMap[$field] ?? 'o.createdAt', $this->sanitizeDirection($direction));
            }
        } else {
            $qb->orderBy('o.createdAt', 'DESC');
        }

        return $qb;
    }

    public function findRecent(int $limit = 8): array
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.createdBy', 'u')
            ->addSelect('u')
            ->orderBy('o.createdAt', 'DESC')
            ->where('o.status != :draftStatus')
            ->setParameter('draftStatus', OperationStatus::DRAFT)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getLastNumber(string $prefix, string $year, string $month): int
    {
        $max = $this->createQueryBuilder('o')
            ->select('MAX(o.number)')
            ->andWhere('o.fullNumber LIKE :prefix')
            ->andWhere('YEAR(o.documentDate) = :year')
            ->andWhere('MONTH(o.documentDate) = :month')
            ->setParameter('prefix', $prefix.'/%')
            ->setParameter('year', $year)
            ->setParameter('month', $month)
            ->getQuery()
            ->getSingleScalarResult();

        return $max ?? 0;
    }
}
