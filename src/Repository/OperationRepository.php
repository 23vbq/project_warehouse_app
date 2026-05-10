<?php

namespace App\Repository;

use App\Entity\Operation;
use App\Entity\Receipt;
use App\Entity\Release;
use App\Entity\Relocation;
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

    public function createIndexQueryBuilder(array $filters = [], array $orderBy = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.createdBy', 'u');

        if (!empty($filters['type'])) {
            $qb->andWhere('o INSTANCE OF :type')
                ->setParameter('type', $filters['type']);
        }

        if (!empty($filters['query'])) {
            if (is_numeric($filters['query'])) {
                $qb->andWhere('o.fullNumber LIKE :query')
                    ->setParameter('query', '%'.$filters['query'].'%');
            }
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

    public function getNextNumber(
        string $type,
        string $year,
        string $month,
    ): int {
        $from = new \DateTimeImmutable(sprintf('%s-%s-01 00:00:00', $year, $month));
        $to = $from->modify('first day of next month');

        $typeMap = [
            Operation::TYPE_RECEIPT => Receipt::class,
            Operation::TYPE_RELEASE => Release::class,
            Operation::TYPE_RELOCATION => Relocation::class,
        ];

        $max = $this->createQueryBuilder('o')
            ->select('MAX(o.number)')
            ->where('o INSTANCE OF :type')
            ->andWhere('o.documentDate >= :from')
            ->andWhere('o.documentDate < :to')
            ->setParameter('type', $typeMap[$type] ?? $type)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        return (null === $max) ? 1 : ((int) $max + 1);
    }
}
