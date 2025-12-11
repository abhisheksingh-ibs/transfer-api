<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "accounts")]
class Account
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: "bigint")]
private ?int $id;

    #[ORM\Column(type: "bigint")]
private int $userId;

    #[ORM\Column(type: "string", length: 3)]
private string $currency = 'INR';

    #[ORM\Column(type: "bigint")]
private int $balance = 0;

    #[ORM\Column(type: "string", length: 20)]
private string $status = 'active';

    // getters & setters...
    public function getId(): int { return $this->id; }
    public function getBalance(): int { return $this->balance; }
    public function setBalance(int $amount): self { $this->balance = $amount; return $this; }
    public function increaseBalance(int $amount): self { $this->balance += $amount; return $this; }
    public function decreaseBalance(int $amount): self { $this->balance -= $amount; return $this; }
}
