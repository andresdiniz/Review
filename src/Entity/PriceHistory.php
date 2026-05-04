<?php
// src/Entity/PriceHistory.php

namespace App\Entity;

use App\Repository\PriceHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PriceHistoryRepository::class)]
#[ORM\Table(name: 'price_history')]
class PriceHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $price = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $recordedAt = null;

    public function getId(): ?int { return $this->id; }

    public function getProduct(): ?Product { return $this->product; }
    public function setProduct(?Product $product): self { $this->product = $product; return $this; }

    public function getPrice(): ?string { return $this->price; }
    public function setPrice(string $price): self { $this->price = $price; return $this; }

    public function getRecordedAt(): ?\DateTimeImmutable { return $this->recordedAt; }
    public function setRecordedAt(\DateTimeImmutable $recordedAt): self { $this->recordedAt = $recordedAt; return $this; }

    #[ORM\PrePersist]
    public function setRecordedAtValue(): void
    {
        if ($this->recordedAt === null) {
            $this->recordedAt = new \DateTimeImmutable();
        }
    }
}
