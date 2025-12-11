<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name:"idempotency_keys")]
class IdempotencyKey
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type:"bigint")]
private int $id;

    #[ORM\Column(type:"string", length:255, unique:true)]
private string $idempotencyKey;

    #[ORM\Column(type:"string", length:20)]
private string $status = 'in_progress'; // in_progress|completed|failed

    #[ORM\Column(type:"bigint", nullable:true)]
private ?int $transferId = null;

    #[ORM\Column(type:"datetime")]
private \DateTimeInterface $createdAt;

    // getters/setters...
}
