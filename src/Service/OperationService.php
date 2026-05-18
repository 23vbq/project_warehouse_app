<?php

namespace App\Service;

use App\Entity\Adjustment;
use App\Entity\Correction;
use App\Entity\Operation;
use App\Entity\Receipt;
use App\Entity\Release;
use App\Entity\Relocation;
use App\Entity\User;
use App\Enum\OperationStatus;
use App\Repository\OperationRepository;

class OperationService
{
    public function __construct(
        private readonly OperationRepository $operationRepository,
        private readonly StockService $stockService,
    ) {
    }

    public function generateNumber(Operation $operation): Operation
    {
        if (null !== $operation->getNumber()) {
            throw new \DomainException('Number is already set for this operation.');
        }

        $prefixMap = [
            Operation::TYPE_RECEIPT => 'PZ',
            Operation::TYPE_RELEASE => 'WZ',
            Operation::TYPE_RELOCATION => 'MM',
            Operation::TYPE_ADJUSTMENT => 'INW',
        ];

        $documentType = $operation->getDocumentType();

        if ($operation instanceof Correction) {
            $correctedType = $operation->getCorrectedOperation()->getDocumentType();
            if (!isset($prefixMap[$correctedType])) {
                throw new \InvalidArgumentException('Invalid corrected document type: '.$correctedType);
            }
            $correctionPrefix = 'K';
            $prefix = $correctionPrefix.$prefixMap[$correctedType];
        } elseif (isset($prefixMap[$documentType])) {
            $prefix = $prefixMap[$documentType];
        } else {
            throw new \InvalidArgumentException('Invalid document type: '.$documentType);
        }

        $documentDate = $operation->getDocumentDate();
        $nextNumber = $this->operationRepository->getLastNumber(
            $documentType,
            $documentDate->format('Y'),
            $documentDate->format('m')
        ) + 1;

        $operation->setNumber($nextNumber);

        $fullNumber = sprintf('%s/%s/%s/%04d', $prefix, $documentDate->format('Y'), $documentDate->format('m'), $nextNumber);
        $operation->setFullNumber($fullNumber);

        return $operation;
    }

    public function confirm(Operation $operation, User $confirmedBy): Operation
    {
        $this->validateForConfirmation($operation);

        if ($operation instanceof Receipt) {
            $this->confirmReceipt($operation);
        } elseif ($operation instanceof Release) {
            $this->confirmRelease($operation);
        } elseif ($operation instanceof Relocation) {
            $this->confirmRelocation($operation);
        } elseif ($operation instanceof Adjustment) {
            $this->confirmAdjustment($operation);
        } elseif ($operation instanceof Correction) {
            $this->confirmCorrection($operation);
        }

        $operation->setStatus(OperationStatus::CONFIRMED);
        $operation->setConfirmedAt(new \DateTimeImmutable());
        $operation->setConfirmedBy($confirmedBy);
        $this->operationRepository->save($operation, true);

        return $operation;
    }

    private function confirmReceipt(Receipt $operation): void
    {
        foreach ($operation->getOperationLines() as $line) {
            $this->stockService->add(
                $line->getProduct(),
                $line->getLocationTo(),
                $line->getQuantity()
            );
        }
    }

    private function confirmRelease(Release $operation): void
    {
        foreach ($operation->getOperationLines() as $line) {
            $this->stockService->subtract(
                $line->getProduct(),
                $line->getLocationFrom(),
                $line->getQuantity()
            );
        }
    }

    private function confirmAdjustment(Adjustment $operation): void
    {
        foreach ($operation->getOperationLines() as $line) {
            if (null !== $line->getLocationTo()) {
                $this->stockService->add($line->getProduct(), $line->getLocationTo(), $line->getQuantity());
            } else {
                $this->stockService->subtract($line->getProduct(), $line->getLocationFrom(), $line->getQuantity());
            }
        }
    }

    private function confirmRelocation(Relocation $operation): void
    {
        foreach ($operation->getOperationLines() as $line) {
            $this->stockService->subtract(
                $line->getProduct(),
                $line->getLocationFrom(),
                $line->getQuantity()
            );

            $this->stockService->add(
                $line->getProduct(),
                $line->getLocationTo(),
                $line->getQuantity()
            );
        }
    }

    private function validateForConfirmation(Operation $operation): void
    {
        if (OperationStatus::DRAFT !== $operation->getStatus()) {
            throw new \DomainException('Operacja musi mieć status DRAFT.');
        }

        if (null === $operation->getDocumentDate()) {
            throw new \DomainException('Data dokumentu jest wymagana do zatwierdzenia.');
        }

        if ($operation instanceof Receipt) {
            $this->validateReceiptForConfirmation($operation);
        } elseif ($operation instanceof Release) {
            $this->validateReleaseForConfirmation($operation);
        } elseif ($operation instanceof Relocation) {
            $this->validateRelocationForConfirmation($operation);
        } elseif ($operation instanceof Adjustment) {
            $this->validateAdjustmentForConfirmation($operation);
        } elseif ($operation instanceof Correction) {
            $this->validateCorrectionForConfirmation($operation);
        }
    }

    private function validateReceiptForConfirmation(Receipt $operation): void
    {
        if (null === $operation->getSupplier()) {
            throw new \DomainException('Dostawca jest wymagany do zatwierdzenia przyjęcia.');
        }

        foreach ($operation->getOperationLines() as $line) {
            if (null === $line->getQuantity()) {
                throw new \DomainException(sprintf('Ilość jest wymagana dla pozycji "%s".', $line->getProduct()->getName()));
            }
            if (null === $line->getLocationTo()) {
                throw new \DomainException(sprintf('Lokalizacja docelowa jest wymagana dla pozycji "%s".', $line->getProduct()->getName()));
            }
            if (null === $line->getUnitPrice()) {
                throw new \DomainException(sprintf('Cena jednostkowa jest wymagana dla pozycji "%s".', $line->getProduct()->getName()));
            }
        }
    }

    private function validateReleaseForConfirmation(Release $operation): void
    {
        if (null === $operation->getRecipient()) {
            throw new \DomainException('Odbiorca jest wymagany do zatwierdzenia wydania.');
        }

        if (null === $operation->getReleaseDate()) {
            throw new \DomainException('Data wydania jest wymagana do zatwierdzenia wydania.');
        }

        foreach ($operation->getOperationLines() as $line) {
            if (null === $line->getQuantity()) {
                throw new \DomainException(sprintf('Ilość jest wymagana dla pozycji "%s".', $line->getProduct()->getName()));
            }
            if (null === $line->getLocationFrom()) {
                throw new \DomainException(sprintf('Lokalizacja źródłowa jest wymagana dla pozycji "%s".', $line->getProduct()->getName()));
            }
            if (null === $line->getUnitPrice()) {
                throw new \DomainException(sprintf('Cena jednostkowa jest wymagana dla pozycji "%s".', $line->getProduct()->getName()));
            }
        }
    }

    private function validateRelocationForConfirmation(Relocation $operation): void
    {
        foreach ($operation->getOperationLines() as $line) {
            if (null === $line->getQuantity()) {
                throw new \DomainException(sprintf('Ilość jest wymagana dla pozycji "%s".', $line->getProduct()->getName()));
            }

            $locationFrom = $line->getLocationFrom();
            $locationTo = $line->getLocationTo();

            if (null === $locationFrom) {
                throw new \DomainException(sprintf('Lokalizacja źródłowa jest wymagana dla pozycji "%s".', $line->getProduct()->getName()));
            }
            if (null === $locationTo) {
                throw new \DomainException(sprintf('Lokalizacja docelowa jest wymagana dla pozycji "%s".', $line->getProduct()->getName()));
            }
            if ($locationFrom === $locationTo) {
                throw new \DomainException(sprintf('Lokalizacja źródłowa i docelowa nie mogą być takie same dla pozycji "%s".', $line->getProduct()->getName()));
            }
        }
    }

    private function validateAdjustmentForConfirmation(Adjustment $operation): void
    {
        foreach ($operation->getOperationLines() as $line) {
            if (null === $line->getQuantity()) {
                continue;
            }

            $hasLocationFrom = null !== $line->getLocationFrom();
            $hasLocationTo = null !== $line->getLocationTo();

            if ($hasLocationFrom === $hasLocationTo) {
                throw new \DomainException(sprintf('Pozycja "%s" musi mieć ustawioną dokładnie jedną lokalizację (źródłową lub docelową).', $line->getProduct()->getName()));
            }
        }
    }

    private function confirmCorrection(Correction $operation): void
    {
        foreach ($operation->getOperationLines() as $line) {
            if (null !== $line->getLocationTo()) {
                $this->stockService->add($line->getProduct(), $line->getLocationTo(), $line->getQuantity());
            } else {
                $this->stockService->subtract($line->getProduct(), $line->getLocationFrom(), $line->getQuantity());
            }
        }
    }

    private function validateCorrectionForConfirmation(Correction $operation): void
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
}
