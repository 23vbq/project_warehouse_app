<?php

namespace App\Entity;

use App\Repository\AdjustmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdjustmentRepository::class)]
class Adjustment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'adjustment', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Stocktaking $stocktaking = null;

    public function getId(): ?int
    {
        return $this->id;
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
