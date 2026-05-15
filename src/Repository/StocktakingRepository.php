<?php

namespace App\Repository;

use App\Entity\Stocktaking;
use App\Traits\SanitizesOrderBy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Stocktaking>
 */
class StocktakingRepository extends ServiceEntityRepository
{
    use SanitizesOrderBy;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stocktaking::class);
    }

    public function createIndexQueryBuilder(array $filters = [], array $orderBy = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('st')
            ->leftJoin('st.createdBy', 'u')
            ->addSelect('u');

        if (!empty($filters['query'])) {
            $qb->andWhere('st.id = :query')
                ->setParameter('query', (int) $filters['query']);
        }

        $orderMap = [
            'createdAt' => 'st.createdAt',
            'status' => 'st.status',
            'completedAt' => 'st.completedAt',
        ];

        if (!empty($orderBy)) {
            foreach ($orderBy as $field => $direction) {
                $qb->addOrderBy($orderMap[$field] ?? 'st.createdAt', $this->sanitizeDirection($direction));
            }
        } else {
            $qb->orderBy('st.createdAt', 'DESC');
        }

        return $qb;
    }

    //    /**
    //     * @return Stocktaking[] Returns an array of Stocktaking objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Stocktaking
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
