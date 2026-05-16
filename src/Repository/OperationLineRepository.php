<?php

namespace App\Repository;

use App\Entity\OperationLine;
use App\Entity\Product;
use App\Enum\OperationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OperationLine>
 */
class OperationLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OperationLine::class);
    }

    public function findDailyNetByProduct(Product $product, \DateTimeImmutable $from): array
    {
        return $this->createQueryBuilder('ol')
            ->select(
                'DATE(o.documentDate) as day',
                'SUM(CASE WHEN ol.locationTo IS NOT NULL AND ol.locationFrom IS NULL THEN ol.quantity ELSE 0 END)
               - SUM(CASE WHEN ol.locationFrom IS NOT NULL AND ol.locationTo IS NULL THEN ol.quantity ELSE 0 END)
                 as netChange'
            )
            ->innerJoin('ol.operation', 'o')
            ->where('ol.product = :product')
            ->andWhere('o.documentDate >= :from')
            ->groupBy('day')
            ->orderBy('day', 'ASC')
            ->setParameter('product', $product)
            ->setParameter('from', $from)
            ->getQuery()
            ->getResult();
    }

    public function findPeriodStatsByProduct(Product $product, \DateTimeImmutable $from): array
    {
        return $this->createQueryBuilder('ol')
            ->select(
                'SUM(CASE WHEN ol.locationTo IS NOT NULL AND ol.locationFrom IS NULL THEN ol.quantity ELSE 0 END) as totalReceived',
                'SUM(CASE WHEN ol.locationFrom IS NOT NULL AND ol.locationTo IS NULL THEN ol.quantity ELSE 0 END) as totalReleased',
                'COUNT(DISTINCT CASE WHEN ol.locationTo IS NOT NULL AND ol.locationFrom IS NULL THEN o.id ELSE :null END) as receiptsCount',
                'COUNT(DISTINCT CASE WHEN ol.locationFrom IS NOT NULL AND ol.locationTo IS NULL THEN o.id ELSE :null END) as releasesCount'
            )
            ->innerJoin('ol.operation', 'o')
            ->where('ol.product = :product')
            ->andWhere('o.documentDate >= :from')
            ->setParameter('null', null)
            ->setParameter('product', $product)
            ->setParameter('from', $from)
            ->getQuery()
            ->getSingleResult();
    }

    public function findDailyActivityForPeriod(\DateTimeImmutable $from): array
    {
        return $this->createQueryBuilder('ol')
            ->select(
                'DATE(o.documentDate) as day',
                'COUNT(DISTINCT CASE WHEN ol.locationTo IS NOT NULL AND ol.locationFrom IS NULL THEN o.id ELSE :null END) as receiptsCount',
                'COUNT(DISTINCT CASE WHEN ol.locationFrom IS NOT NULL AND ol.locationTo IS NULL THEN o.id ELSE :null END) as releasesCount'
            )
            ->innerJoin('ol.operation', 'o')
            ->where('o.documentDate >= :from')
            ->andWhere('o.status != :draftStatus')
            ->groupBy('day')
            ->orderBy('day', 'ASC')
            ->setParameter('null', null)
            ->setParameter('from', $from)
            ->setParameter('draftStatus', OperationStatus::DRAFT)
            ->getQuery()
            ->getResult();
    }

    public function createByProductQueryBuilder(Product $product): QueryBuilder
    {
        return $this->createQueryBuilder('ol')
            ->innerJoin('ol.operation', 'o')
            ->where('ol.product = :product')
            ->orderBy('o.documentDate', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setParameter('product', $product);
    }
}
