<?php

namespace App\Entity;

use App\Repository\CorrectionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CorrectionRepository::class)]
class Correction extends Operation
{
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Operation $correctedOperation = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reason = null;

    public function getDocumentType(): string
    {
        return Operation::TYPE_CORRECTION;
    }

    public function getCorrectedOperation(): ?Operation
    {
        return $this->correctedOperation;
    }

    public function setCorrectedOperation(?Operation $correctedOperation): static
    {
        $this->correctedOperation = $correctedOperation;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }
}
