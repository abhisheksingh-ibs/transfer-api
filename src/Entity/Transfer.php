<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "transfers")]
class Transfer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "bigint")]
private ?int $id = null;

    #[ORM\Column(type: "bigint")]
private int $fromAccountId;

    #[ORM\Column(type: "bigint")]
private int $toAccountId;

    #[ORM\Column(type: "bigint")]
private int $amount;

    #[ORM\Column(type: "string", length: 3)]
private string $currency;

    #[ORM\Column(type: "string", length: 20)]
private string $status;

    #[ORM\Column(type: "json", nullable: true)]
private array $metadata = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFromAccountId(): int
    {
        return $this->fromAccountId;
    }

    public function setFromAccountId(int $id): self
    {
        $this->fromAccountId = $id;
        return $this;
    }

    public function getToAccountId(): int
    {
        return $this->toAccountId;
    }

    public function setToAccountId(int $id): self
    {
        $this->toAccountId = $id;
        return $this;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }
}
