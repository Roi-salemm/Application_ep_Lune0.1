<?php

namespace App\Entity;

use App\Repository\IaAdminLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IaAdminLogRepository::class)]
#[ORM\Table(name: 'ia_admin_log')]
#[ORM\Index(columns: ['created_at'], name: 'idx_ia_admin_log_created_at')]
#[ORM\HasLifecycleCallbacks]
class IaAdminLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'created_by_user_id', type: Types::BIGINT, nullable: true, options: ['unsigned' => true])]
    private ?string $createdByUserId = null;

    #[ORM\Column(type: Types::STRING, length: 40, options: ['default' => 'admin'])]
    private string $source = 'admin';

    #[ORM\Column(name: 'received_json', type: Types::JSON, nullable: true)]
    private ?array $receivedJson = null;

    #[ORM\Column(name: 'intent_json', type: Types::JSON, nullable: true)]
    private ?array $intentJson = null;

    #[ORM\Column(name: 'pipeline_json', type: Types::JSON, nullable: true)]
    private ?array $pipelineJson = null;

    #[ORM\Column(name: 'final_prompt_text', type: Types::TEXT, nullable: true, columnDefinition: 'LONGTEXT')]
    private ?string $finalPromptText = null;

    #[ORM\Column(name: 'request_payload_json', type: Types::JSON, nullable: true)]
    private ?array $requestPayloadJson = null;

    #[ORM\Column(name: 'response_payload_json', type: Types::JSON, nullable: true)]
    private ?array $responsePayloadJson = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function ensureCreatedAt(): void
    {
        if (!isset($this->createdAt)) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCreatedByUserId(): ?string
    {
        return $this->createdByUserId;
    }

    public function setCreatedByUserId(?string $createdByUserId): self
    {
        $this->createdByUserId = $createdByUserId;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getReceivedJson(): ?array
    {
        return $this->receivedJson;
    }

    public function setReceivedJson(?array $receivedJson): self
    {
        $this->receivedJson = $receivedJson;

        return $this;
    }

    public function getIntentJson(): ?array
    {
        return $this->intentJson;
    }

    public function setIntentJson(?array $intentJson): self
    {
        $this->intentJson = $intentJson;

        return $this;
    }

    public function getPipelineJson(): ?array
    {
        return $this->pipelineJson;
    }

    public function setPipelineJson(?array $pipelineJson): self
    {
        $this->pipelineJson = $pipelineJson;

        return $this;
    }

    public function getFinalPromptText(): ?string
    {
        return $this->finalPromptText;
    }

    public function setFinalPromptText(?string $finalPromptText): self
    {
        $this->finalPromptText = $finalPromptText;

        return $this;
    }

    public function getRequestPayloadJson(): ?array
    {
        return $this->requestPayloadJson;
    }

    public function setRequestPayloadJson(?array $requestPayloadJson): self
    {
        $this->requestPayloadJson = $requestPayloadJson;

        return $this;
    }

    public function getResponsePayloadJson(): ?array
    {
        return $this->responsePayloadJson;
    }

    public function setResponsePayloadJson(?array $responsePayloadJson): self
    {
        $this->responsePayloadJson = $responsePayloadJson;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }
}
