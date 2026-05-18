<?php

namespace App\Service;

use App\Entity\Correction;
use App\Entity\Location;
use App\Entity\Operation;
use App\Entity\OperationLine;

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
     * Same product + same location → single delta line (quantity difference).
     * Changed product or location  → full reversal of original + application of desired.
     * Line removed from form       → full reversal of original.
     * Extra lines beyond original  → passed through as-is.
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
                $result[] = $this->createReversalLine($originalLine, $documentType);
                continue;
            }

            $sameProduct = $desiredLine->getProduct()?->getId() === $originalLine->getProduct()?->getId();

            [$expectedFrom, $expectedTo] = $this->getReversalLocations($originalLine, $documentType);

            $locationChanged = $desiredLine->getLocationFrom()?->getId() !== $expectedFrom?->getId()
                || $desiredLine->getLocationTo()?->getId() !== $expectedTo?->getId();

            if (!$sameProduct || $locationChanged) {
                $result[] = $this->createReversalLine($originalLine, $documentType);
                $result[] = $this->createApplicationLine($desiredLine);
                continue;
            }

            $delta = bcsub($originalLine->getQuantity(), $desiredLine->getQuantity(), OperationLine::QUANTITY_SCALE);
            $cmp = bccomp($delta, '0', OperationLine::QUANTITY_SCALE);

            if (0 === $cmp) {
                continue;
            }

            $absDelta = $cmp > 0 ? $delta : bcsub('0', $delta, OperationLine::QUANTITY_SCALE);

            $deltaLine = new OperationLine();
            $deltaLine->setProduct($desiredLine->getProduct());
            $deltaLine->setQuantity($absDelta);

            if ($cmp > 0) {
                $deltaLine->setLocationFrom($desiredLine->getLocationFrom());
                $deltaLine->setLocationTo($desiredLine->getLocationTo());
            } else {
                $deltaLine->setLocationFrom($desiredLine->getLocationTo());
                $deltaLine->setLocationTo($desiredLine->getLocationFrom());
            }

            $result[] = $deltaLine;
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

    private function createReversalLine(OperationLine $originalLine, string $documentType): OperationLine
    {
        [$locationFrom, $locationTo] = $this->getReversalLocations($originalLine, $documentType);

        $line = new OperationLine();
        $line->setProduct($originalLine->getProduct());
        $line->setQuantity($originalLine->getQuantity());
        $line->setLocationFrom($locationFrom);
        $line->setLocationTo($locationTo);

        return $line;
    }

    /**
     * Creates a line applying the desired state in the original direction.
     * The form's locationFrom/locationTo represent the correction direction — swapping gives the original direction.
     */
    private function createApplicationLine(OperationLine $desiredLine): OperationLine
    {
        $line = new OperationLine();
        $line->setProduct($desiredLine->getProduct());
        $line->setQuantity($desiredLine->getQuantity());
        $line->setLocationFrom($desiredLine->getLocationTo());
        $line->setLocationTo($desiredLine->getLocationFrom());

        return $line;
    }
}
