<?php

namespace App\Service;

use App\Entity\Correction;
use App\Entity\Location;
use App\Entity\Operation;
use App\Entity\OperationLine;
use App\Entity\Product;

class CorrectionService
{
    /** Document types that can have corrections created against them. */
    public const CORRECTABLE_TYPES = [
        Operation::TYPE_RECEIPT,
        Operation::TYPE_RELEASE,
        Operation::TYPE_RELOCATION,
        Operation::TYPE_ADJUSTMENT,
    ];

    public function __construct(
        private readonly StockService $stockService,
    ) {
    }

    /**
     * Replaces the correction's operationLines with the given pre-computed lines.
     * Always call computeLines() first (passing effective lines as base when prior confirmed
     * corrections exist) and pass the result here — do not re-derive lines from the corrected
     * operation directly, as that would ignore the effective state from prior corrections.
     *
     * @param OperationLine[] $computed result of computeLines()
     */
    public function buildLines(Correction $correction, array $computed): void
    {
        foreach ($correction->getOperationLines()->toArray() as $line) {
            $correction->removeOperationLine($line);
        }
        foreach ($computed as $line) {
            $correction->addOperationLine($line);
        }
    }

    /**
     * Pure computation: given the desired state per line (from the form) and the base lines,
     * returns the actual correction OperationLines to be persisted.
     *
     * $baseLines should be the effective lines (original + all prior confirmed corrections) so that
     * successive corrections always work against the current warehouse state, not the original document.
     * Pass original operation lines when no confirmed corrections exist yet.
     *
     * Same product + same location → delta line(s) (quantity difference).
     * Changed product or location  → full reversal of base line + application of desired.
     * Line removed from form       → full reversal of base line.
     *
     * Relocation lines always produce two XOR lines (subtract + add) to satisfy the
     * constraint that every OperationLine has exactly one location set.
     *
     * @param OperationLine[] $desiredLines
     * @param OperationLine[] $baseLines    effective lines or original operation lines
     *
     * @return OperationLine[]
     */
    public function computeLines(array $desiredLines, array $baseLines, string $documentType): array
    {
        $originalLines = array_values($baseLines);
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
                if (Operation::TYPE_RELOCATION === $documentType) {
                    // For relocations the form is pre-filled in correction direction (locations already reversed).
                    // The desired line is therefore already the correction — use it directly.
                    // Reversal of the base is needed when the product changed OR when the source location
                    // changed (user picked a different source than the expected one). Without reversal the
                    // original source's stock contribution would remain unaddressed, producing wrong totals.
                    // When only the destination changes the source stays the same so no reversal is needed.
                    $sourceChanged = $desiredLine->getLocationFrom()?->getId() !== $expectedFrom?->getId();
                    if (!$sameProduct || $sourceChanged) {
                        array_push($result, ...$this->createReversalLines($originalLine, $documentType));
                    }
                    array_push($result, ...$this->createSplitLines(
                        $desiredLine->getProduct(),
                        $desiredLine->getQuantity(),
                        $desiredLine->getLocationFrom(),
                        $desiredLine->getLocationTo()
                    ));
                } else {
                    array_push($result, ...$this->createReversalLines($originalLine, $documentType));
                    array_push($result, ...$this->createApplicationLines($desiredLine));
                }

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

        return $result;
    }

    /**
     * Computes the net OperationLines for an operation after applying all its confirmed corrections.
     *
     * Works by decomposing every original line into signed (product, location) contributions,
     * applying confirmed correction lines the same way, then converting the accumulated totals
     * back to XOR OperationLine objects (exactly one location set, always positive quantity).
     * Entries that cancel out to zero are omitted.
     *
     * Returns an empty array when there are no confirmed corrections (caller should skip
     * the section entirely — original lines are already the effective state).
     *
     * @param Correction[] $corrections
     *
     * @return OperationLine[]
     */
    public function computeEffectiveLines(Operation $operation, array $corrections): array
    {
        $confirmedCorrections = array_filter($corrections, fn (Correction $c) => $c->isConfirmed());

        if (empty($confirmedCorrections)) {
            return [];
        }

        $acc = [];

        foreach ($operation->getOperationLines() as $line) {
            if (null !== $line->getLocationTo()) {
                $this->accumulateSigned($acc, $line->getProduct(), $line->getLocationTo(), $line->getQuantity());
            }
            if (null !== $line->getLocationFrom()) {
                $this->accumulateSigned($acc, $line->getProduct(), $line->getLocationFrom(), bcsub('0', $line->getQuantity(), OperationLine::QUANTITY_SCALE));
            }
        }

        foreach ($confirmedCorrections as $correction) {
            foreach ($correction->getOperationLines() as $line) {
                if (null !== $line->getLocationTo()) {
                    $this->accumulateSigned($acc, $line->getProduct(), $line->getLocationTo(), $line->getQuantity());
                } else {
                    $this->accumulateSigned($acc, $line->getProduct(), $line->getLocationFrom(), bcsub('0', $line->getQuantity(), OperationLine::QUANTITY_SCALE));
                }
            }
        }

        $result = [];
        foreach ($acc as ['product' => $product, 'location' => $location, 'quantity' => $signedQty]) {
            if (0 === bccomp($signedQty, '0', OperationLine::QUANTITY_SCALE)) {
                continue;
            }

            $line = new OperationLine();
            $line->setProduct($product);

            if (bccomp($signedQty, '0', OperationLine::QUANTITY_SCALE) > 0) {
                $line->setLocationTo($location);
                $line->setQuantity($signedQty);
            } else {
                $line->setLocationFrom($location);
                $line->setQuantity(bcsub('0', $signedQty, OperationLine::QUANTITY_SCALE));
            }

            $result[] = $line;
        }

        // For relocations, pair the +/− XOR lines back into from→to entries so the display
        // can show "B-01 → B-03 : 15" instead of two separate "+B-03" and "−B-01" rows.
        if (Operation::TYPE_RELOCATION === $operation->getDocumentType()) {
            return $this->pairRelocationLines($result);
        }

        return $result;
    }

    /**
     * Greedily pairs single-location OperationLines (one locationTo / one locationFrom) of the
     * same product into dual-location lines (both set).  Lines that cannot be paired are kept
     * as-is so the display can still show orphaned net movements.
     *
     * @param OperationLine[] $lines XOR lines from computeEffectiveLines
     *
     * @return OperationLine[]
     */
    private function pairRelocationLines(array $lines): array
    {
        $positive = [];
        $negative = [];

        foreach ($lines as $line) {
            $pid = $line->getProduct()->getId();
            if (null !== $line->getLocationTo()) {
                $positive[$pid][] = ['product' => $line->getProduct(), 'location' => $line->getLocationTo(), 'qty' => $line->getQuantity()];
            } else {
                $negative[$pid][] = ['product' => $line->getProduct(), 'location' => $line->getLocationFrom(), 'qty' => $line->getQuantity()];
            }
        }

        $result = [];
        $allPids = array_unique(array_merge(array_keys($positive), array_keys($negative)));

        foreach ($allPids as $pid) {
            $plusList = $positive[$pid] ?? [];
            $minusList = $negative[$pid] ?? [];
            $pi = 0;
            $mi = 0;

            while ($pi < count($plusList) && $mi < count($minusList)) {
                $pqty = $plusList[$pi]['qty'];
                $mqty = $minusList[$mi]['qty'];
                $pairedQty = bccomp($pqty, $mqty, OperationLine::QUANTITY_SCALE) <= 0 ? $pqty : $mqty;

                $paired = new OperationLine();
                $paired->setProduct($plusList[$pi]['product']);
                $paired->setQuantity($pairedQty);
                $paired->setLocationFrom($minusList[$mi]['location']);
                $paired->setLocationTo($plusList[$pi]['location']);
                $result[] = $paired;

                $plusList[$pi]['qty'] = bcsub($pqty, $pairedQty, OperationLine::QUANTITY_SCALE);
                $minusList[$mi]['qty'] = bcsub($mqty, $pairedQty, OperationLine::QUANTITY_SCALE);

                if (0 === bccomp($plusList[$pi]['qty'], '0', OperationLine::QUANTITY_SCALE)) {
                    ++$pi;
                }
                if (0 === bccomp($minusList[$mi]['qty'], '0', OperationLine::QUANTITY_SCALE)) {
                    ++$mi;
                }
            }

            // Unpaired remainders — keep as XOR lines
            for ($i = $pi; $i < count($plusList); ++$i) {
                if (0 !== bccomp($plusList[$i]['qty'], '0', OperationLine::QUANTITY_SCALE)) {
                    $l = new OperationLine();
                    $l->setProduct($plusList[$i]['product']);
                    $l->setQuantity($plusList[$i]['qty']);
                    $l->setLocationTo($plusList[$i]['location']);
                    $result[] = $l;
                }
            }
            for ($i = $mi; $i < count($minusList); ++$i) {
                if (0 !== bccomp($minusList[$i]['qty'], '0', OperationLine::QUANTITY_SCALE)) {
                    $l = new OperationLine();
                    $l->setProduct($minusList[$i]['product']);
                    $l->setQuantity($minusList[$i]['qty']);
                    $l->setLocationFrom($minusList[$i]['location']);
                    $result[] = $l;
                }
            }
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

            if (!$hasLocationFrom && !$hasLocationTo) {
                throw new \DomainException(sprintf('Pozycja "%s" nie ma ustawionej żadnej lokalizacji — wymagana jest lokalizacja źródłowa lub docelowa.', $line->getProduct()->getName()));
            }

            if ($hasLocationFrom && $hasLocationTo) {
                throw new \DomainException(sprintf('Pozycja "%s" ma ustawione obie lokalizacje jednocześnie — dozwolona jest tylko jedna (źródłowa lub docelowa).', $line->getProduct()->getName()));
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

    private function accumulateSigned(array &$acc, Product $product, Location $location, string $signedQty): void
    {
        $key = $product->getId().'_'.$location->getId();
        if (!isset($acc[$key])) {
            $acc[$key] = ['product' => $product, 'location' => $location, 'quantity' => '0'];
        }
        $acc[$key]['quantity'] = bcadd($acc[$key]['quantity'], $signedQty, OperationLine::QUANTITY_SCALE);
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
