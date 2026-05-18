<?php

namespace App\Service;

use App\Entity\Correction;
use App\Entity\Location;
use App\Entity\Operation;
use App\Entity\OperationLine;
use App\Entity\Product;

class CorrectionService
{
    public function __construct(
        private readonly StockService $stockService,
    ) {
    }

    public function buildLines(Correction $correction, Operation $correctedOperation): void
    {
        $desiredLines = array_values($correction->getOperationLines()->toArray());
        $computed = $this->computeLines($desiredLines, $correctedOperation);

        foreach ($correction->getOperationLines()->toArray() as $line) {
            $correction->removeOperationLine($line);
        }
        foreach ($computed as $line) {
            $correction->addOperationLine($line);
        }
    }

    /**
     * Pure computation: given the desired state per line (from the form) and the original operation,
     * returns the actual correction OperationLines to be persisted.
     *
     * Same product + same location → delta line(s) (quantity difference).
     * Changed product or location  → full reversal of original + application of desired.
     * Line removed from form       → full reversal of original.
     * Extra lines beyond original  → passed through as-is.
     *
     * Relocation lines always produce two XOR lines (subtract + add) to satisfy the
     * constraint that every OperationLine has exactly one location set.
     *
     * @param OperationLine[] $desiredLines
     *
     * @return OperationLine[]
     */
    public function computeLines(array $desiredLines, Operation $correctedOperation): array
    {
        $originalLines = array_values($correctedOperation->getOperationLines()->toArray());
        $documentType = $correctedOperation->getDocumentType();
        $result = [];

        foreach ($originalLines as $i => $originalLine) {
            $desiredLine = $desiredLines[$i] ?? null;

            if (null === $desiredLine) {
                array_push($result, ...$this->createReversalLines($originalLine, $documentType));
                continue;
            }

            $sameProduct = $desiredLine->getProduct()?->getId() === $originalLine->getProduct()?->getId();

            [$expectedFrom, $expectedTo] = $this->getReversalLocations($originalLine, $documentType);

            $locationChanged = $desiredLine->getLocationFrom()?->getId() !== $expectedFrom?->getId()
                || $desiredLine->getLocationTo()?->getId() !== $expectedTo?->getId();

            if (!$sameProduct || $locationChanged) {
                array_push($result, ...$this->createReversalLines($originalLine, $documentType));
                array_push($result, ...$this->createApplicationLines($desiredLine));
                continue;
            }

            $delta = bcsub($originalLine->getQuantity(), $desiredLine->getQuantity(), OperationLine::QUANTITY_SCALE);
            $cmp = bccomp($delta, '0', OperationLine::QUANTITY_SCALE);

            if (0 === $cmp) {
                continue;
            }

            $absDelta = $cmp > 0 ? $delta : bcsub('0', $delta, OperationLine::QUANTITY_SCALE);

            if ($cmp > 0) {
                // original > desired: delta in the correction direction (as shown in the form)
                $deltaLines = $this->createSplitLines(
                    $desiredLine->getProduct(),
                    $absDelta,
                    $desiredLine->getLocationFrom(),
                    $desiredLine->getLocationTo()
                );
            } else {
                // desired > original: delta in the opposite direction
                $deltaLines = $this->createSplitLines(
                    $desiredLine->getProduct(),
                    $absDelta,
                    $desiredLine->getLocationTo(),
                    $desiredLine->getLocationFrom()
                );
            }

            array_push($result, ...$deltaLines);
        }

        foreach (array_slice($desiredLines, count($originalLines)) as $extraLine) {
            $result[] = $extraLine;
        }

        return $result;
    }

    public function confirm(Correction $operation): void
    {
        foreach ($operation->getOperationLines() as $line) {
            if (null !== $line->getLocationTo()) {
                $this->stockService->add($line->getProduct(), $line->getLocationTo(), $line->getQuantity());
            } else {
                $this->stockService->subtract($line->getProduct(), $line->getLocationFrom(), $line->getQuantity());
            }
        }
    }

    public function validateForConfirmation(Correction $operation): void
    {
        if ($operation->getOperationLines()->isEmpty()) {
            throw new \DomainException('Korekta musi zawierać co najmniej jedną pozycję.');
        }

        foreach ($operation->getOperationLines() as $line) {
            if (null === $line->getQuantity()) {
                throw new \DomainException(sprintf('Ilość jest wymagana dla pozycji "%s".', $line->getProduct()->getName()));
            }

            $hasLocationFrom = null !== $line->getLocationFrom();
            $hasLocationTo = null !== $line->getLocationTo();

            if ($hasLocationFrom === $hasLocationTo) {
                throw new \DomainException(sprintf('Pozycja "%s" musi mieć ustawioną dokładnie jedną lokalizację (źródłową lub docelową).', $line->getProduct()->getName()));
            }
        }
    }

    /**
     * @return array{0: Location|null, 1: Location|null}
     */
    private function getReversalLocations(OperationLine $originalLine, string $documentType): array
    {
        return match ($documentType) {
            Operation::TYPE_RECEIPT => [$originalLine->getLocationTo(), null],
            Operation::TYPE_RELEASE => [null, $originalLine->getLocationFrom()],
            default => [$originalLine->getLocationTo(), $originalLine->getLocationFrom()],
        };
    }

    /** @return OperationLine[] */
    private function createReversalLines(OperationLine $originalLine, string $documentType): array
    {
        [$locationFrom, $locationTo] = $this->getReversalLocations($originalLine, $documentType);

        return $this->createSplitLines($originalLine->getProduct(), $originalLine->getQuantity(), $locationFrom, $locationTo);
    }

    /**
     * Creates line(s) applying the desired state in the original direction.
     * The form's locationFrom/locationTo represent the correction direction — swapping gives the original direction.
     *
     * @return OperationLine[]
     */
    private function createApplicationLines(OperationLine $desiredLine): array
    {
        return $this->createSplitLines(
            $desiredLine->getProduct(),
            $desiredLine->getQuantity(),
            $desiredLine->getLocationTo(),
            $desiredLine->getLocationFrom()
        );
    }

    /**
     * Creates one or two OperationLines depending on whether both locations are non-null.
     * If both are non-null (relocation case), splits into a subtract line (locationFrom only)
     * and an add line (locationTo only), ensuring every line has exactly one location set.
     *
     * @return OperationLine[]
     */
    private function createSplitLines(?Product $product, string $quantity, ?Location $locationFrom, ?Location $locationTo): array
    {
        if (null !== $locationFrom && null !== $locationTo) {
            $subtractLine = new OperationLine();
            $subtractLine->setProduct($product);
            $subtractLine->setQuantity($quantity);
            $subtractLine->setLocationFrom($locationFrom);

            $addLine = new OperationLine();
            $addLine->setProduct($product);
            $addLine->setQuantity($quantity);
            $addLine->setLocationTo($locationTo);

            return [$subtractLine, $addLine];
        }

        $line = new OperationLine();
        $line->setProduct($product);
        $line->setQuantity($quantity);
        $line->setLocationFrom($locationFrom);
        $line->setLocationTo($locationTo);

        return [$line];
    }
}
