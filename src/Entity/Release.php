<?php

namespace App\Entity;

use App\Repository\ReleaseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReleaseRepository::class)]
#[ORM\Table(name: '`release`')]
class Release extends Operation
{
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $recipient = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customerOrderNumber = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $releaseDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $releaseMethod = null;

    public function getRecipient(): ?string
    {
        return $this->recipient;
    }

    public function setRecipient(?string $recipient): static
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getCustomerOrderNumber(): ?string
    {
        return $this->customerOrderNumber;
    }

    public function setCustomerOrderNumber(?string $customerOrderNumber): static
    {
        $this->customerOrderNumber = $customerOrderNumber;

        return $this;
    }

    public function getReleaseDate(): ?\DateTimeImmutable
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(?\DateTimeImmutable $releaseDate): static
    {
        $this->releaseDate = $releaseDate;

        return $this;
    }

    public function getReleaseMethod(): ?string
    {
        return $this->releaseMethod;
    }

    public function setReleaseMethod(?string $releaseMethod): static
    {
        $this->releaseMethod = $releaseMethod;

        return $this;
    }
}
