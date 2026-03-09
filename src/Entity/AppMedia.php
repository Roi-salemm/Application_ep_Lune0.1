<?php

namespace App\Entity;

use App\Repository\AppMediaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entite media pour stocker des variantes d image (mini/tel/tab) et les metadonnees d upload.
 * Pourquoi: servir la bonne taille par appareil sans conserver l original sur disque.
 * Info: les chemins sont relatifs au dossier public (uploads/app_media/...).
 */
#[ORM\Entity(repositoryClass: AppMediaRepository::class)]
#[ORM\Table(name: 'app_media')]
#[ORM\HasLifecycleCallbacks]
class AppMedia
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\Column(type: Types::STRING, length: 20, columnDefinition: "ENUM('image','audio','video','document')")]
    private string $type;

    #[ORM\Column(name: 'original_path', type: Types::STRING, length: 255, nullable: true)]
    private ?string $originalPath = null;

    #[ORM\Column(name: 'app_path', type: Types::STRING, length: 255)]
    private string $appPath;

    #[ORM\Column(name: 'original_mime', type: Types::STRING, length: 120)]
    private string $originalMime;

    #[ORM\Column(name: 'app_mime', type: Types::STRING, length: 120)]
    private string $appMime;

    #[ORM\Column(name: 'original_size', type: Types::INTEGER, options: ['unsigned' => true])]
    private int $originalSize;

    #[ORM\Column(name: 'app_size', type: Types::INTEGER, options: ['unsigned' => true])]
    private int $appSize;

    #[ORM\Column(name: 'app_width', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $appWidth = null;

    #[ORM\Column(name: 'app_height', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $appHeight = null;

    #[ORM\Column(name: 'img_mini_path', type: Types::STRING, length: 255, nullable: true)]
    private ?string $imgMiniPath = null;

    #[ORM\Column(name: 'img_mini_mime', type: Types::STRING, length: 120, nullable: true)]
    private ?string $imgMiniMime = null;

    #[ORM\Column(name: 'img_mini_size', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $imgMiniSize = null;

    #[ORM\Column(name: 'img_mini_width', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $imgMiniWidth = null;

    #[ORM\Column(name: 'img_mini_height', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $imgMiniHeight = null;

    #[ORM\Column(name: 'img_tel_path', type: Types::STRING, length: 255, nullable: true)]
    private ?string $imgTelPath = null;

    #[ORM\Column(name: 'img_tel_mime', type: Types::STRING, length: 120, nullable: true)]
    private ?string $imgTelMime = null;

    #[ORM\Column(name: 'img_tel_size', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $imgTelSize = null;

    #[ORM\Column(name: 'img_tel_width', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $imgTelWidth = null;

    #[ORM\Column(name: 'img_tel_height', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $imgTelHeight = null;

    #[ORM\Column(name: 'img_tab_path', type: Types::STRING, length: 255, nullable: true)]
    private ?string $imgTabPath = null;

    #[ORM\Column(name: 'img_tab_mime', type: Types::STRING, length: 120, nullable: true)]
    private ?string $imgTabMime = null;

    #[ORM\Column(name: 'img_tab_size', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $imgTabSize = null;

    #[ORM\Column(name: 'img_tab_width', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $imgTabWidth = null;

    #[ORM\Column(name: 'img_tab_height', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $imgTabHeight = null;

    #[ORM\Column(name: 'is_public', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isPublic = false;

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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getOriginalPath(): ?string
    {
        return $this->originalPath;
    }

    public function setOriginalPath(?string $originalPath): self
    {
        $this->originalPath = $originalPath;

        return $this;
    }

    public function getAppPath(): string
    {
        return $this->appPath;
    }

    public function setAppPath(string $appPath): self
    {
        $this->appPath = $appPath;

        return $this;
    }

    public function getOriginalMime(): string
    {
        return $this->originalMime;
    }

    public function setOriginalMime(string $originalMime): self
    {
        $this->originalMime = $originalMime;

        return $this;
    }

    public function getAppMime(): string
    {
        return $this->appMime;
    }

    public function setAppMime(string $appMime): self
    {
        $this->appMime = $appMime;

        return $this;
    }

    public function getOriginalSize(): int
    {
        return $this->originalSize;
    }

    public function setOriginalSize(int $originalSize): self
    {
        $this->originalSize = $originalSize;

        return $this;
    }

    public function getAppSize(): int
    {
        return $this->appSize;
    }

    public function setAppSize(int $appSize): self
    {
        $this->appSize = $appSize;

        return $this;
    }

    public function getAppWidth(): ?int
    {
        return $this->appWidth;
    }

    public function setAppWidth(?int $appWidth): self
    {
        $this->appWidth = $appWidth;

        return $this;
    }

    public function getAppHeight(): ?int
    {
        return $this->appHeight;
    }

    public function setAppHeight(?int $appHeight): self
    {
        $this->appHeight = $appHeight;

        return $this;
    }

    public function getImgMiniPath(): ?string
    {
        return $this->imgMiniPath;
    }

    public function setImgMiniPath(?string $imgMiniPath): self
    {
        $this->imgMiniPath = $imgMiniPath;

        return $this;
    }

    public function getImgMiniMime(): ?string
    {
        return $this->imgMiniMime;
    }

    public function setImgMiniMime(?string $imgMiniMime): self
    {
        $this->imgMiniMime = $imgMiniMime;

        return $this;
    }

    public function getImgMiniSize(): ?int
    {
        return $this->imgMiniSize;
    }

    public function setImgMiniSize(?int $imgMiniSize): self
    {
        $this->imgMiniSize = $imgMiniSize;

        return $this;
    }

    public function getImgMiniWidth(): ?int
    {
        return $this->imgMiniWidth;
    }

    public function setImgMiniWidth(?int $imgMiniWidth): self
    {
        $this->imgMiniWidth = $imgMiniWidth;

        return $this;
    }

    public function getImgMiniHeight(): ?int
    {
        return $this->imgMiniHeight;
    }

    public function setImgMiniHeight(?int $imgMiniHeight): self
    {
        $this->imgMiniHeight = $imgMiniHeight;

        return $this;
    }

    public function getImgTelPath(): ?string
    {
        return $this->imgTelPath;
    }

    public function setImgTelPath(?string $imgTelPath): self
    {
        $this->imgTelPath = $imgTelPath;

        return $this;
    }

    public function getImgTelMime(): ?string
    {
        return $this->imgTelMime;
    }

    public function setImgTelMime(?string $imgTelMime): self
    {
        $this->imgTelMime = $imgTelMime;

        return $this;
    }

    public function getImgTelSize(): ?int
    {
        return $this->imgTelSize;
    }

    public function setImgTelSize(?int $imgTelSize): self
    {
        $this->imgTelSize = $imgTelSize;

        return $this;
    }

    public function getImgTelWidth(): ?int
    {
        return $this->imgTelWidth;
    }

    public function setImgTelWidth(?int $imgTelWidth): self
    {
        $this->imgTelWidth = $imgTelWidth;

        return $this;
    }

    public function getImgTelHeight(): ?int
    {
        return $this->imgTelHeight;
    }

    public function setImgTelHeight(?int $imgTelHeight): self
    {
        $this->imgTelHeight = $imgTelHeight;

        return $this;
    }

    public function getImgTabPath(): ?string
    {
        return $this->imgTabPath;
    }

    public function setImgTabPath(?string $imgTabPath): self
    {
        $this->imgTabPath = $imgTabPath;

        return $this;
    }

    public function getImgTabMime(): ?string
    {
        return $this->imgTabMime;
    }

    public function setImgTabMime(?string $imgTabMime): self
    {
        $this->imgTabMime = $imgTabMime;

        return $this;
    }

    public function getImgTabSize(): ?int
    {
        return $this->imgTabSize;
    }

    public function setImgTabSize(?int $imgTabSize): self
    {
        $this->imgTabSize = $imgTabSize;

        return $this;
    }

    public function getImgTabWidth(): ?int
    {
        return $this->imgTabWidth;
    }

    public function setImgTabWidth(?int $imgTabWidth): self
    {
        $this->imgTabWidth = $imgTabWidth;

        return $this;
    }

    public function getImgTabHeight(): ?int
    {
        return $this->imgTabHeight;
    }

    public function setImgTabHeight(?int $imgTabHeight): self
    {
        $this->imgTabHeight = $imgTabHeight;

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): self
    {
        $this->isPublic = $isPublic;

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
