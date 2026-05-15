<?php

namespace App\Entity;

use App\Enum\StocktakingStatus;
use App\Repository\StocktakingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StocktakingRepository::class)]
class Stocktaking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: StocktakingStatus::class)]
    private ?StocktakingStatus $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    private ?User $createdBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\ManyToOne]
    private ?User $completedBy = null;

    /**
     * @var Collection<int, StocktakingLine>
     */
    #[ORM\OneToMany(targetEntity: StocktakingLine::class, mappedBy: 'stocktaking', cascade: ['persist'], orphanRemoval: true)]
    private Collection $stocktakingLines;

    #[ORM\OneToOne(mappedBy: 'stocktaking', cascade: ['persist', 'remove'])]
    private ?Adjustment $adjustment = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->stocktakingLines = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): ?StocktakingStatus
    {
        return $this->status;
    }

    public function setStatus(StocktakingStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    private function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    public function getCompletedBy(): ?User
    {
        return $this->completedBy;
    }

    public function setCompletedBy(?User $completedBy): static
    {
        $this->completedBy = $completedBy;

        return $this;
    }

    /**
     * @return Collection<int, StocktakingLine>
     */
    public function getStocktakingLines(): Collection
    {
        return $this->stocktakingLines;
    }

    public function addStocktakingLine(StocktakingLine $stocktakingLine): static
    {
        if (!$this->stocktakingLines->contains($stocktakingLine)) {
            $this->stocktakingLines->add($stocktakingLine);
            $stocktakingLine->setStocktaking($this);
        }

        return $this;
    }

    public function removeStocktakingLine(StocktakingLine $stocktakingLine): static
    {
        if ($this->stocktakingLines->removeElement($stocktakingLine)) {
            // set the owning side to null (unless already changed)
            if ($stocktakingLine->getStocktaking() === $this) {
                $stocktakingLine->setStocktaking(null);
            }
        }

        return $this;
    }

    public function getAdjustment(): ?Adjustment
    {
        return $this->adjustment;
    }

    public function setAdjustment(Adjustment $adjustment): static
    {
        // set the owning side of the relation if necessary
        if ($adjustment->getStocktaking() !== $this) {
            $adjustment->setStocktaking($this);
        }

        $this->adjustment = $adjustment;

        return $this;
    }
}
