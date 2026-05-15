<?php

namespace App\Entity;

use App\Repository\AdjustmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdjustmentRepository::class)]
class Adjustment extends Operation
{
    #[ORM\OneToOne(inversedBy: 'adjustment', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Stocktaking $stocktaking = null;

    public function getDocumentType(): string
    {
        return Operation::TYPE_ADJUSTMENT;
    }

    public function getStocktaking(): ?Stocktaking
    {
        return $this->stocktaking;
    }

    public function setStocktaking(Stocktaking $stocktaking): static
    {
        $this->stocktaking = $stocktaking;

        return $this;
    }
}
