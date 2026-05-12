<?php

namespace App\Entity;

use App\Repository\OperationLineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OperationLineRepository::class)]
class OperationLine
{
    public const QUANTITY_SCALE = 3;
    public const PRICE_SCALE = 2;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'operationLines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Operation $operation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\ManyToOne]
    private ?Location $locationFrom = null;

    #[ORM\ManyToOne]
    private ?Location $locationTo = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: self::QUANTITY_SCALE)]
    private ?string $quantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: self::PRICE_SCALE, nullable: true)]
    private ?string $unitPrice = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOperation(): ?Operation
    {
        return $this->operation;
    }

    public function setOperation(?Operation $operation): static
    {
        $this->operation = $operation;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getLocationFrom(): ?Location
    {
        return $this->locationFrom;
    }

    public function setLocationFrom(?Location $locationFrom): static
    {
        $this->locationFrom = $locationFrom;

        return $this;
    }

    public function getLocationTo(): ?Location
    {
        return $this->locationTo;
    }

    public function setLocationTo(?Location $locationTo): static
    {
        $this->locationTo = $locationTo;

        return $this;
    }

    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(?string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }
}
