<?php

namespace App\Entity;

use App\Repository\RelocationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RelocationRepository::class)]
class Relocation extends Operation
{
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reason = null;

    public function getDocumentType(): string
    {
        return 'relocation';
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
