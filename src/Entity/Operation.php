<?php

namespace App\Entity;

use App\Enum\OperationStatus;
use App\Repository\OperationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OperationRepository::class)]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
#[ORM\DiscriminatorMap([
    Operation::TYPE_RECEIPT => Receipt::class,
    Operation::TYPE_RELEASE => Release::class,
    Operation::TYPE_RELOCATION => Relocation::class,
])]
abstract class Operation
{
    public const TYPE_RECEIPT = 'receipt';
    public const TYPE_RELEASE = 'release';
    public const TYPE_RELOCATION = 'relocation';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $number = null;

    #[ORM\Column(enumType: OperationStatus::class)]
    private ?OperationStatus $status = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $documentDate = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\ManyToOne]
    private ?User $confirmedBy = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    private ?User $createdBy = null;

    /**
     * @var Collection<int, OperationLine>
     */
    #[ORM\OneToMany(targetEntity: OperationLine::class, mappedBy: 'operation', cascade: ['persist'], orphanRemoval: true)]
    private Collection $operationLines;

    #[ORM\Column(length: 255)]
    private ?string $fullNumber = null;

    public function __construct()
    {
        $this->status = OperationStatus::DRAFT;
        $this->createdAt = new \DateTimeImmutable();
        $this->operationLines = new ArrayCollection();
    }

    abstract public function getDocumentType(): string;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function setNumber(int $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getStatus(): ?OperationStatus
    {
        return $this->status;
    }

    public function setStatus(OperationStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getDocumentDate(): ?\DateTimeImmutable
    {
        return $this->documentDate;
    }

    public function setDocumentDate(?\DateTimeImmutable $documentDate): static
    {
        $this->documentDate = $documentDate;

        return $this;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function setConfirmedAt(?\DateTimeImmutable $confirmedAt): static
    {
        $this->confirmedAt = $confirmedAt;

        return $this;
    }

    public function getConfirmedBy(): ?User
    {
        return $this->confirmedBy;
    }

    public function setConfirmedBy(?User $confirmedBy): static
    {
        $this->confirmedBy = $confirmedBy;

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

    /**
     * @return Collection<int, OperationLine>
     */
    public function getOperationLines(): Collection
    {
        return $this->operationLines;
    }

    public function addOperationLine(OperationLine $operationLine): static
    {
        if (!$this->operationLines->contains($operationLine)) {
            $this->operationLines->add($operationLine);
            $operationLine->setOperation($this);
        }

        return $this;
    }

    public function removeOperationLine(OperationLine $operationLine): static
    {
        $this->operationLines->removeElement($operationLine);

        return $this;
    }

    public function getFullNumber(): ?string
    {
        return $this->fullNumber;
    }

    public function setFullNumber(string $fullNumber): static
    {
        $this->fullNumber = $fullNumber;

        return $this;
    }

    public function isConfirmed(): bool
    {
        return $this->status === OperationStatus::CONFIRMED;
    }
}
