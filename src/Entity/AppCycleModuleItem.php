<?php

namespace App\Entity;

use App\Repository\AppCycleModuleItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Item de module dans un cycle (article, media, ressource ou outil).
 * Pourquoi: lister des contenus heterogenes avec un ordre stable par module.
 * Info: l ordre est gere par order_index, et les references sont optionnelles selon le type.
 */
#[ORM\Entity(repositoryClass: AppCycleModuleItemRepository::class)]
#[ORM\Table(name: 'app_cycle_module_item')]
#[ORM\Index(columns: ['module_id', 'order_index'], name: 'idx_cycle_module_item_order')]
#[ORM\Index(columns: ['module_id', 'is_free_preview', 'order_index'], name: 'idx_cycle_module_item_preview')]
#[ORM\Index(columns: ['ref_card_id'], name: 'idx_cycle_module_item_ref_card')]
#[ORM\Index(columns: ['ref_media_id'], name: 'idx_cycle_module_item_ref_media')]
#[ORM\UniqueConstraint(columns: ['module_id', 'order_index'], name: 'uq_cycle_module_item_order')]
#[ORM\HasLifecycleCallbacks]
class AppCycleModuleItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: AppCycleModule::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'module_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?AppCycleModule $module = null;

    #[ORM\Column(name: 'item_type', type: Types::STRING, length: 20, columnDefinition: "ENUM('article','audio','video','resource','tool')")]
    private string $itemType;

    #[ORM\Column(name: 'order_index', type: Types::INTEGER)]
    private int $orderIndex;

    #[ORM\Column(name: 'title_override', type: Types::STRING, length: 255, nullable: true)]
    private ?string $titleOverride = null;

    #[ORM\Column(name: 'is_free_preview', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isFreePreview = false;

    #[ORM\ManyToOne(targetEntity: AppCard::class)]
    #[ORM\JoinColumn(name: 'ref_card_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AppCard $refCard = null;

    #[ORM\ManyToOne(targetEntity: AppMedia::class)]
    #[ORM\JoinColumn(name: 'ref_media_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AppMedia $refMedia = null;

    #[ORM\Column(name: 'external_url', type: Types::STRING, length: 255, nullable: true)]
    private ?string $externalUrl = null;

    #[ORM\Column(name: 'content_json', type: Types::JSON, nullable: true)]
    private ?array $contentJson = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
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

    public function getModule(): ?AppCycleModule
    {
        return $this->module;
    }

    public function setModule(?AppCycleModule $module): self
    {
        $this->module = $module;

        return $this;
    }

    public function getItemType(): string
    {
        return $this->itemType;
    }

    public function setItemType(string $itemType): self
    {
        $this->itemType = $itemType;

        return $this;
    }

    public function getOrderIndex(): int
    {
        return $this->orderIndex;
    }

    public function setOrderIndex(int $orderIndex): self
    {
        $this->orderIndex = $orderIndex;

        return $this;
    }

    public function getTitleOverride(): ?string
    {
        return $this->titleOverride;
    }

    public function setTitleOverride(?string $titleOverride): self
    {
        $this->titleOverride = $titleOverride;

        return $this;
    }

    public function isFreePreview(): bool
    {
        return $this->isFreePreview;
    }

    public function setIsFreePreview(bool $isFreePreview): self
    {
        $this->isFreePreview = $isFreePreview;

        return $this;
    }

    public function getRefCard(): ?AppCard
    {
        return $this->refCard;
    }

    public function setRefCard(?AppCard $refCard): self
    {
        $this->refCard = $refCard;

        return $this;
    }

    public function getRefMedia(): ?AppMedia
    {
        return $this->refMedia;
    }

    public function setRefMedia(?AppMedia $refMedia): self
    {
        $this->refMedia = $refMedia;

        return $this;
    }

    public function getExternalUrl(): ?string
    {
        return $this->externalUrl;
    }

    public function setExternalUrl(?string $externalUrl): self
    {
        $this->externalUrl = $externalUrl;

        return $this;
    }

    public function getContentJson(): ?array
    {
        return $this->contentJson;
    }

    public function setContentJson(?array $contentJson): self
    {
        $this->contentJson = $contentJson;

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
}
