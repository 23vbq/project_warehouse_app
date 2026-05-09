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
            $typeMap = [
                'receipt'    => Receipt::class,
                'release'    => Release::class,
                'relocation' => Relocation::class,
            ];
            if (isset($typeMap[$filters['type']])) {
                $qb->andWhere('o INSTANCE OF :type')
                    ->setParameter('type', $typeMap[$filters['type']]);
            }
        }

        if (!empty($filters['query'])) {
            $qb->andWhere('o.number LIKE :query')
                ->setParameter('query', '%' . $filters['query'] . '%');
        }

        $orderMap = [
            'number'       => 'o.number',
            'documentDate' => 'o.documentDate',
            'createdAt'    => 'o.createdAt',
            'status'       => 'o.status',
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
}
