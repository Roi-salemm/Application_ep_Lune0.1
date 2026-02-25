<?php

namespace App\Entity;

use App\Repository\AppCardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entite card pour le catalogue (article ou cycle) visible dans l app.
 * Pourquoi: separer le meta (card) du contenu detaille (article/cycle).
 * Info: le type determine ou stocker le contenu (app_article_content ou modules de cycle).
 */
#[ORM\Entity(repositoryClass: AppCardRepository::class)]
#[ORM\Table(name: 'app_card')]
#[ORM\UniqueConstraint(columns: ['slug'], name: 'uq_app_card_slug')]
#[ORM\Index(columns: ['status', 'published_at'], name: 'idx_app_card_status_published')]
#[ORM\Index(columns: ['type'], name: 'idx_app_card_type')]
#[ORM\Index(columns: ['access_level'], name: 'idx_app_card_access_level')]
#[ORM\HasLifecycleCallbacks]
class AppCard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\Column(type: Types::STRING, length: 20, columnDefinition: "ENUM('article','cycle')")]
    private string $type;

    #[ORM\Column(type: Types::STRING, length: 190)]
    private string $slug;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $baseline = null;

    #[ORM\ManyToOne(targetEntity: AppMedia::class)]
    #[ORM\JoinColumn(name: 'cover_media_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AppMedia $coverMedia = null;

    #[ORM\Column(name: 'access_level', type: Types::STRING, length: 20, columnDefinition: "ENUM('free','premium')", options: ['default' => 'free'])]
    private string $accessLevel = 'free';

    #[ORM\Column(type: Types::STRING, length: 20, columnDefinition: "ENUM('draft','published')", options: ['default' => 'draft'])]
    private string $status = 'draft';

    #[ORM\Column(name: 'published_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToOne(mappedBy: 'card', targetEntity: AppArticleContent::class)]
    private ?AppArticleContent $articleContent = null;

    /**
     * @var Collection<int, AppCycleModule>
     */
    #[ORM\OneToMany(mappedBy: 'cycleCard', targetEntity: AppCycleModule::class)]
    private Collection $cycleModules;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->cycleModules = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function ensureTimestamps(): void
    {
        if (!isset($this->createdAt)) {
            $this->createdAt = new \DateTimeImmutable();
        }
        if (!isset($this->updatedAt)) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getBaseline(): ?string
    {
        return $this->baseline;
    }

    public function setBaseline(?string $baseline): self
    {
        $this->baseline = $baseline;

        return $this;
    }

    public function getCoverMedia(): ?AppMedia
    {
        return $this->coverMedia;
    }

    public function setCoverMedia(?AppMedia $coverMedia): self
    {
        $this->coverMedia = $coverMedia;

        return $this;
    }

    public function getAccessLevel(): string
    {
        return $this->accessLevel;
    }

    public function setAccessLevel(string $accessLevel): self
    {
        $this->accessLevel = $accessLevel;

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

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getArticleContent(): ?AppArticleContent
    {
        return $this->articleContent;
    }

    public function setArticleContent(?AppArticleContent $articleContent): self
    {
        $this->articleContent = $articleContent;

        return $this;
    }

    /**
     * @return Collection<int, AppCycleModule>
     */
    public function getCycleModules(): Collection
    {
        return $this->cycleModules;
    }

    public function addCycleModule(AppCycleModule $module): self
    {
        if (!$this->cycleModules->contains($module)) {
            $this->cycleModules->add($module);
            $module->setCycleCard($this);
        }

        return $this;
    }

    public function removeCycleModule(AppCycleModule $module): self
    {
        if ($this->cycleModules->removeElement($module)) {
            if ($module->getCycleCard() === $this) {
                $module->setCycleCard(null);
            }
        }

        return $this;
    }
}
