<?php

namespace App\Repository;

use App\Entity\Stocktaking;
use App\Entity\StocktakingLine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StocktakingLine>
 */
class StocktakingLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StocktakingLine::class);
    }

    /**
     * @return StocktakingLine[]
     */
    public function findSortedByStocktaking(Stocktaking $stocktaking): array
    {
        return $this->createQueryBuilder('sl')
            ->innerJoin('sl.location', 'l')
            ->innerJoin('sl.product', 'p')
            ->addSelect('l', 'p')
            ->where('sl.stocktaking = :stocktaking')
            ->orderBy('l.code', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->setParameter('stocktaking', $stocktaking)
            ->getQuery()
            ->getResult();
    }
}
