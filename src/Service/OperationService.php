<?php

namespace App\Service;

use App\Entity\Operation;
use App\Enum\OperationStatus;
use App\Repository\OperationRepository;

class OperationService
{
    public function __construct(
        private readonly OperationRepository $operationRepository,
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
        ];

        $documentType = $operation->getDocumentType();

        if (!isset($prefixMap[$documentType])) {
            throw new \InvalidArgumentException('Invalid document type: '.$documentType);
        }

        $documentDate = $operation->getDocumentDate();
        $nextNumber = $this->operationRepository->getNextNumber(
            $operation->getDocumentType(),
            $documentDate->format('Y'),
            $documentDate->format('m')
        );

        $operation->setNumber($nextNumber);

        $prefix = $prefixMap[$operation->getDocumentType()];
        $fullNumber = sprintf('%s/%s/%s/%04d', $prefix, $documentDate->format('Y'), $documentDate->format('m'), $nextNumber);
        $operation->setFullNumber($fullNumber);

        return $operation;
    }

    public function confirm(Operation $operation): Operation
    {
        if (OperationStatus::DRAFT !== $operation->getStatus()) {
            throw new \DomainException('Operacja musi mieć status DRAFT');
        }

        $this->validateForConfirmation($operation);

        return $operation;
    }

    protected function validateForConfirmation(Operation $operation): void
    {
        if ($operation->getOperationLines()->isEmpty()) {
            throw new \DomainException('Nie można zatwierdzić operacji bez pozycji.');
        }

        if (null === $operation->getDocumentDate()) {
            throw new \DomainException('Data dokumentu jest wymagana do zatwierdzenia.');
        }

        $documentType = $operation->getDocumentType();
        if (Operation::TYPE_RELEASE === $documentType) {
        } elseif (Operation::TYPE_RELOCATION === $documentType) {
        } elseif (Operation::TYPE_RECEIPT === $documentType) {
        }
    }
}
