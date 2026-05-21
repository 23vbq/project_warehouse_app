<?php

namespace App\Repository;

use App\Entity\Correction;
use App\Entity\Operation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Correction>
 */
class CorrectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Correction::class);
    }

    /**
     * Returns corrections for the given operation with createdBy and operationLines eagerly loaded
     * to avoid N+1 queries when iterating lines (e.g. in computeEffectiveLines).
     *
     * @return Correction[]
     */
    public function findByCorrectedOperationWithUsers(Operation $operation, string $order = 'DESC'): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.createdBy', 'u')
            ->addSelect('u')
            ->leftJoin('c.operationLines', 'l')
            ->addSelect('l')
            ->where('c.correctedOperation = :operation')
            ->setParameter('operation', $operation)
            ->orderBy('c.createdAt', $order)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns a map of [correctionId => lineCount] for all corrections of the given operation.
     * Use this instead of accessing correction.operationLines|length in Twig to avoid N+1 queries.
     *
     * @return array<int, int>
     */
    public function countLinesByCorrectedOperation(Operation $operation): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.id, COUNT(l.id) as lineCount')
            ->leftJoin('c.operationLines', 'l')
            ->where('c.correctedOperation = :operation')
            ->setParameter('operation', $operation)
            ->groupBy('c.id')
            ->getQuery()
            ->getResult();

        return array_map('intval', array_column($rows, 'lineCount', 'id'));
    }
}
