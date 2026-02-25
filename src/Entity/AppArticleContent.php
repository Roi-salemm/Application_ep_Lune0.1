<?php

namespace App\Entity;

use App\Repository\AppArticleContentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Contenu detaille pour une card de type article.
 * Pourquoi: stocker le corps Tiptap et les metadonnees specifiques a l article.
 * Info: la PK est aussi la FK vers app_card (relation 1-1).
 */
#[ORM\Entity(repositoryClass: AppArticleContentRepository::class)]
#[ORM\Table(name: 'app_article_content')]
class AppArticleContent
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: AppCard::class, inversedBy: 'articleContent')]
    #[ORM\JoinColumn(name: 'card_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private AppCard $card;

    #[ORM\Column(name: 'body_json', type: Types::JSON)]
    private array $bodyJson = [];

    #[ORM\Column(name: 'reading_minutes', type: Types::SMALLINT, nullable: true, options: ['unsigned' => true])]
    private ?int $readingMinutes = null;

    #[ORM\ManyToOne(targetEntity: AppMedia::class)]
    #[ORM\JoinColumn(name: 'hero_media_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AppMedia $heroMedia = null;

    public function __construct(AppCard $card)
    {
        $this->card = $card;
    }

    public function getCard(): AppCard
    {
        return $this->card;
    }

    public function setCard(AppCard $card): self
    {
        $this->card = $card;

        return $this;
    }

    public function getBodyJson(): array
    {
        return $this->bodyJson;
    }

    public function setBodyJson(array $bodyJson): self
    {
        $this->bodyJson = $bodyJson;

        return $this;
    }

    public function getReadingMinutes(): ?int
    {
        return $this->readingMinutes;
    }

    public function setReadingMinutes(?int $readingMinutes): self
    {
        $this->readingMinutes = $readingMinutes;

        return $this;
    }

    public function getHeroMedia(): ?AppMedia
    {
        return $this->heroMedia;
    }

    public function setHeroMedia(?AppMedia $heroMedia): self
    {
        $this->heroMedia = $heroMedia;

        return $this;
    }
}
