<?php

namespace App\Service;

use App\Entity\Adjustment;
use App\Entity\OperationLine;
use App\Entity\Stock;
use App\Entity\Stocktaking;
use App\Entity\StocktakingLine;
use App\Entity\User;
use App\Enum\StocktakingStatus;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;

class StocktakingService
{
    public function __construct(
        private readonly StockRepository $stockRepository,
        private readonly OperationService $operationService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function create(Stocktaking $stocktaking): void
    {
        $stocks = $this->stockRepository->findAll();

        foreach ($stocks as $stock) {
            $line = (new StocktakingLine())
                ->setProduct($stock->getProduct())
                ->setLocation($stock->getLocation())
                ->setExpectedQuantity($stock->getQuantity());

            $stocktaking->addStocktakingLine($line);
        }

        $stocktaking->setStatus(StocktakingStatus::OPEN);
        $this->entityManager->persist($stocktaking);
        $this->entityManager->flush();
    }

    public function saveLine(StocktakingLine $line, ?string $countedQuantity, User $savedBy): void
    {
        $stocktaking = $line->getStocktaking();

        if (!$stocktaking->isActive()) {
            throw new \DomainException('Nie można modyfikować linii zakończonej lub anulowanej inwentaryzacji.');
        }

        if (null !== $countedQuantity && (!is_numeric($countedQuantity) || bccomp($countedQuantity, '0', Stock::QUANTITY_SCALE) < 0)) {
            throw new \DomainException('Podana ilość jest nieprawidłowa.');
        }

        $line->setCountedQuantity($countedQuantity);
        if (null !== $countedQuantity) {
            $line->setSavedAt(new \DateTimeImmutable());
            $line->setSavedBy($savedBy);
        } else {
            $line->setSavedAt(null);
            $line->setSavedBy(null);
        }

        if (StocktakingStatus::OPEN === $stocktaking->getStatus()) {
            $stocktaking->setStatus(StocktakingStatus::IN_PROGRESS);
        }

        $this->entityManager->flush();
    }

    public function complete(Stocktaking $stocktaking, User $completedBy): void
    {
        if (!$stocktaking->isActive()) {
            throw new \DomainException('Nie można zatwierdzić zakończonej lub anulowanej inwentaryzacji.');
        }

        $adjustment = new Adjustment();
        $adjustment->setDocumentDate(new \DateTimeImmutable());
        $adjustment->setCreatedBy($completedBy);
        $adjustment->setStocktaking($stocktaking);

        foreach ($stocktaking->getStocktakingLines() as $line) {
            if (!$line->isSaved()) {
                continue;
            }

            $diff = bcsub($line->getCountedQuantity(), $line->getExpectedQuantity(), Stock::QUANTITY_SCALE);

            if (0 === bccomp($diff, '0', Stock::QUANTITY_SCALE)) {
                continue;
            }

            $operationLine = new OperationLine();
            $operationLine->setProduct($line->getProduct());

            if (bccomp($diff, '0', Stock::QUANTITY_SCALE) > 0) {
                $operationLine->setLocationTo($line->getLocation());
                $operationLine->setQuantity($diff);
            } else {
                $operationLine->setLocationFrom($line->getLocation());
                $operationLine->setQuantity(bcsub('0', $diff, Stock::QUANTITY_SCALE));
            }

            $adjustment->addOperationLine($operationLine);
        }

        $stocktaking->setStatus(StocktakingStatus::COMPLETED);
        $stocktaking->setCompletedAt(new \DateTimeImmutable());
        $stocktaking->setCompletedBy($completedBy);

        $this->entityManager->persist($adjustment);
        $this->operationService->generateNumber($adjustment);
        $this->operationService->confirm($adjustment, $completedBy);
    }

    public function cancel(Stocktaking $stocktaking, User $cancelledBy): void
    {
        if (!$stocktaking->isActive()) {
            throw new \DomainException('Nie można anulować zakończonej lub anulowanej inwentaryzacji.');
        }

        $stocktaking->setStatus(StocktakingStatus::CANCELLED);
        $stocktaking->setCompletedAt(new \DateTimeImmutable());
        $stocktaking->setCompletedBy($cancelledBy);
        $this->entityManager->flush();
    }
}
