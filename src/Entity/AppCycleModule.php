<?php

namespace App\Entity;

use App\Repository\AppCycleModuleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Module d un cycle, ordonne et publie dans un cycle specifique.
 * Pourquoi: decouper un cycle en sections affichables et reutilisables.
 * Info: l ordre est gere par order_index avec contrainte unique par cycle.
 */
#[ORM\Entity(repositoryClass: AppCycleModuleRepository::class)]
#[ORM\Table(name: 'app_cycle_module')]
#[ORM\Index(columns: ['cycle_card_id', 'order_index'], name: 'idx_cycle_module_order')]
#[ORM\UniqueConstraint(columns: ['cycle_card_id', 'order_index'], name: 'uq_cycle_module_order')]
#[ORM\HasLifecycleCallbacks]
class AppCycleModule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: AppCard::class, inversedBy: 'cycleModules')]
    #[ORM\JoinColumn(name: 'cycle_card_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?AppCard $cycleCard = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $baseline = null;

    #[ORM\Column(name: 'order_index', type: Types::INTEGER)]
    private int $orderIndex;

    #[ORM\Column(name: 'is_published', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isPublished = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, AppCycleModuleItem>
     */
    #[ORM\OneToMany(mappedBy: 'module', targetEntity: AppCycleModuleItem::class)]
    private Collection $items;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->items = new ArrayCollection();
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

    public function getCycleCard(): ?AppCard
    {
        return $this->cycleCard;
    }

    public function setCycleCard(?AppCard $cycleCard): self
    {
        $this->cycleCard = $cycleCard;

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

    public function getOrderIndex(): int
    {
        return $this->orderIndex;
    }

    public function setOrderIndex(int $orderIndex): self
    {
        $this->orderIndex = $orderIndex;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): self
    {
        $this->isPublished = $isPublished;

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

    /**
     * @return Collection<int, AppCycleModuleItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(AppCycleModuleItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setModule($this);
        }

        return $this;
    }

    public function removeItem(AppCycleModuleItem $item): self
    {
        if ($this->items->removeElement($item)) {
            if ($item->getModule() === $this) {
                $item->setModule(null);
            }
        }

        return $this;
    }
}
