<?php

namespace App\Entity;

use App\Repository\StocktakingLineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StocktakingLineRepository::class)]
class StocktakingLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'stocktakingLines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Stocktaking $stocktaking = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Location $location = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3)]
    private ?string $expectedQuantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3, nullable: true)]
    private ?string $countedQuantity = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $savedAt = null;

    #[ORM\ManyToOne]
    private ?User $savedBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStocktaking(): ?Stocktaking
    {
        return $this->stocktaking;
    }

    public function setStocktaking(?Stocktaking $stocktaking): static
    {
        $this->stocktaking = $stocktaking;

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

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function setLocation(?Location $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getExpectedQuantity(): ?string
    {
        return $this->expectedQuantity;
    }

    public function setExpectedQuantity(string $expectedQuantity): static
    {
        $this->expectedQuantity = $expectedQuantity;

        return $this;
    }

    public function getCountedQuantity(): ?string
    {
        return $this->countedQuantity;
    }

    public function setCountedQuantity(?string $countedQuantity): static
    {
        $this->countedQuantity = $countedQuantity;

        return $this;
    }

    public function getSavedAt(): ?\DateTimeImmutable
    {
        return $this->savedAt;
    }

    public function setSavedAt(?\DateTimeImmutable $savedAt): static
    {
        $this->savedAt = $savedAt;

        return $this;
    }

    public function isSaved(): bool
    {
        return null !== $this->savedAt;
    }

    public function getSavedBy(): ?User
    {
        return $this->savedBy;
    }

    public function setSavedBy(?User $savedBy): static
    {
        $this->savedBy = $savedBy;

        return $this;
    }
}
