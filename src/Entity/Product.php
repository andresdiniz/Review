<?php
// src/Entity/Product.php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $name = null;

    /**
     * @Gedmo\Slug(fields={"name"})
     */
    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $aiVerdict = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $pros = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $cons = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $fullReviewMarkdown = null;

    #[ORM\Column(length: 500)]
    #[Assert\Url]
    private ?string $affiliateLink = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $currentPrice = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $youtubeVideoId = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUpdateAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $mercadolivreId = null;

    /**
     * Nota geral da IA, de 0 a 10.
     * ATENÇÃO: após adicionar este campo, rode:
     *   php bin/console doctrine:migrations:diff
     *   php bin/console doctrine:migrations:migrate
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 1, nullable: true)]
    #[Assert\Range(min: 0, max: 10)]
    private ?string $score = null;

    // ── Getters e Setters ──────────────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getAiVerdict(): ?string
    {
        return $this->aiVerdict;
    }

    public function setAiVerdict(?string $aiVerdict): self
    {
        $this->aiVerdict = $aiVerdict;
        return $this;
    }

    public function getPros(): array
    {
        return $this->pros;
    }

    public function setPros(?array $pros): self
    {
        $this->pros = $pros ?? [];
        return $this;
    }

    public function getCons(): array
    {
        return $this->cons;
    }

    public function setCons(?array $cons): self
    {
        $this->cons = $cons ?? [];
        return $this;
    }

    public function getFullReviewMarkdown(): ?string
    {
        return $this->fullReviewMarkdown;
    }

    public function setFullReviewMarkdown(?string $fullReviewMarkdown): self
    {
        $this->fullReviewMarkdown = $fullReviewMarkdown;
        return $this;
    }

    public function getAffiliateLink(): ?string
    {
        return $this->affiliateLink;
    }

    public function setAffiliateLink(string $affiliateLink): self
    {
        $this->affiliateLink = $affiliateLink;
        return $this;
    }

    public function getCurrentPrice(): ?string
    {
        return $this->currentPrice;
    }

    public function setCurrentPrice(string $currentPrice): self
    {
        $this->currentPrice = $currentPrice;
        return $this;
    }

    public function getYoutubeVideoId(): ?string
    {
        return $this->youtubeVideoId;
    }

    public function setYoutubeVideoId(?string $youtubeVideoId): self
    {
        $this->youtubeVideoId = $youtubeVideoId;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getLastUpdateAt(): ?\DateTimeImmutable
    {
        return $this->lastUpdateAt;
    }

    public function setLastUpdateAt(?\DateTimeImmutable $lastUpdateAt): self
    {
        $this->lastUpdateAt = $lastUpdateAt;
        return $this;
    }

    /**
     * Alias de lastUpdateAt para uso nos templates Twig como product.updatedAt.
     * Retorna lastUpdateAt quando preenchido, caso contrário retorna createdAt.
     */
    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->lastUpdateAt ?? $this->createdAt;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function getMercadolivreId(): ?string
    {
        return $this->mercadolivreId;
    }

    public function setMercadolivreId(?string $mercadolivreId): self
    {
        $this->mercadolivreId = $mercadolivreId;
        return $this;
    }

    public function getScore(): ?string
    {
        return $this->score;
    }

    public function setScore(?string $score): self
    {
        $this->score = $score;
        return $this;
    }

    // ── Lifecycle Callbacks ────────────────────────────────────────────────

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setLastUpdateAtValue(): void
    {
        $this->lastUpdateAt = new \DateTimeImmutable();
    }
}
