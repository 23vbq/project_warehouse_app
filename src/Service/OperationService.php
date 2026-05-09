<?php

namespace App\Service;

use App\Entity\Operation;
use App\Repository\OperationRepository;

class OperationService
{
    public function __construct(
        private readonly OperationRepository $operationRepository,
    ) {}

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
}