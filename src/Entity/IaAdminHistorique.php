<?php

namespace App\Entity;

use App\Repository\IaAdminHistoriqueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IaAdminHistoriqueRepository::class)]
#[ORM\Table(name: 'ia_admin_historique')]
#[ORM\Index(columns: ['created_at'], name: 'idx_hist_created_at')]
#[ORM\Index(columns: ['prompt_slug', 'prompt_version'], name: 'idx_hist_slug')]
#[ORM\Index(columns: ['success', 'created_at'], name: 'idx_hist_success')]
#[ORM\Index(columns: ['context_key'], name: 'idx_hist_context')]
#[ORM\HasLifecycleCallbacks]
class IaAdminHistorique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: IaAdminLog::class)]
    #[ORM\JoinColumn(name: 'ia_admin_log_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private IaAdminLog $log;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;


    #[ORM\Column(name: 'pipeline_id', type: Types::STRING, length: 36, nullable: true)]
    private ?string $pipelineId = null;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'generation'])]
    private string $stage = 'generation';

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $fingerprint = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $success = false;

    #[ORM\Column(type: Types::STRING, length: 30, options: ['default' => 'ollama'])]
    private string $provider = 'ollama';

    #[ORM\Column(name: 'model_name', type: Types::STRING, length: 120, nullable: true)]
    private ?string $modelName = null;

    #[ORM\Column(name: 'prompt_name', type: Types::STRING, length: 150, nullable: true)]
    private ?string $promptName = null;

    #[ORM\Column(name: 'prompt_slug', type: Types::STRING, length: 180, nullable: true)]
    private ?string $promptSlug = null;

    #[ORM\Column(name: 'prompt_version', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $promptVersion = null;

    #[ORM\Column(name: 'latency_ms', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $latencyMs = null;

    #[ORM\Column(name: 'prompt_tokens', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $promptTokens = null;

    #[ORM\Column(name: 'completion_tokens', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $completionTokens = null;

    #[ORM\Column(name: 'total_tokens', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $totalTokens = null;

    #[ORM\Column(name: 'error_code', type: Types::STRING, length: 80, nullable: true)]
    private ?string $errorCode = null;

    #[ORM\Column(name: 'context_key', type: Types::STRING, length: 160, nullable: true)]
    private ?string $contextKey = null;

    #[ORM\Column(name: 'prompt_client', type: Types::TEXT, nullable: true)]
    private ?string $promptClient = null;

    #[ORM\Column(name: 'final_prompt', type: Types::TEXT, nullable: true, columnDefinition: 'LONGTEXT')]
    private ?string $finalPrompt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, columnDefinition: 'LONGTEXT')]
    private ?string $response = null;

    #[ORM\Column(name: 'intent_raw', type: Types::STRING, length: 40, nullable: true)]
    private ?string $intentRaw = null;

    #[ORM\Column(type: Types::STRING, length: 40, nullable: true)]
    private ?string $intent = null;

    #[ORM\Column(name: 'knowledge_keys', type: Types::JSON, nullable: true)]
    private ?array $knowledgeKeys = null;

    #[ORM\Column(name: 'knowledge_keys_validated', type: Types::JSON, nullable: true)]
    private ?array $knowledgeKeysValidated = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $constraints = null;

    public function __construct(?IaAdminLog $log = null)
    {
        if ($log instanceof IaAdminLog) {
            $this->log = $log;
        }
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

    public function getLog(): IaAdminLog
    {
        return $this->log;
    }

    public function setLog(IaAdminLog $log): self
    {
        $this->log = $log;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPipelineId(): ?string
    {
        return $this->pipelineId;
    }

    public function setPipelineId(?string $pipelineId): self
    {
        $this->pipelineId = $pipelineId;

        return $this;
    }

    public function getStage(): string
    {
        return $this->stage;
    }

    public function setStage(string $stage): self
    {
        $this->stage = $stage;

        return $this;
    }

    public function getFingerprint(): ?string
    {
        return $this->fingerprint;
    }

    public function setFingerprint(?string $fingerprint): self
    {
        $this->fingerprint = $fingerprint;

        return $this;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): self
    {
        $this->success = $success;

        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getModelName(): ?string
    {
        return $this->modelName;
    }

    public function setModelName(?string $modelName): self
    {
        $this->modelName = $modelName;

        return $this;
    }

    public function getPromptName(): ?string
    {
        return $this->promptName;
    }

    public function setPromptName(?string $promptName): self
    {
        $this->promptName = $promptName;

        return $this;
    }

    public function getPromptSlug(): ?string
    {
        return $this->promptSlug;
    }

    public function setPromptSlug(?string $promptSlug): self
    {
        $this->promptSlug = $promptSlug;

        return $this;
    }

    public function getPromptVersion(): ?int
    {
        return $this->promptVersion;
    }

    public function setPromptVersion(?int $promptVersion): self
    {
        $this->promptVersion = $promptVersion;

        return $this;
    }

    public function getLatencyMs(): ?int
    {
        return $this->latencyMs;
    }

    public function setLatencyMs(?int $latencyMs): self
    {
        $this->latencyMs = $latencyMs;

        return $this;
    }

    public function getPromptTokens(): ?int
    {
        return $this->promptTokens;
    }

    public function setPromptTokens(?int $promptTokens): self
    {
        $this->promptTokens = $promptTokens;

        return $this;
    }

    public function getCompletionTokens(): ?int
    {
        return $this->completionTokens;
    }

    public function setCompletionTokens(?int $completionTokens): self
    {
        $this->completionTokens = $completionTokens;

        return $this;
    }

    public function getTotalTokens(): ?int
    {
        return $this->totalTokens;
    }

    public function setTotalTokens(?int $totalTokens): self
    {
        $this->totalTokens = $totalTokens;

        return $this;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function setErrorCode(?string $errorCode): self
    {
        $this->errorCode = $errorCode;

        return $this;
    }

    public function getContextKey(): ?string
    {
        return $this->contextKey;
    }

    public function setContextKey(?string $contextKey): self
    {
        $this->contextKey = $contextKey;

        return $this;
    }

    public function getPromptClient(): ?string
    {
        return $this->promptClient;
    }

    public function setPromptClient(?string $promptClient): self
    {
        $this->promptClient = $promptClient;

        return $this;
    }

    public function getFinalPrompt(): ?string
    {
        return $this->finalPrompt;
    }

    public function setFinalPrompt(?string $finalPrompt): self
    {
        $this->finalPrompt = $finalPrompt;

        return $this;
    }

    public function getResponse(): ?string
    {
        return $this->response;
    }

    public function setResponse(?string $response): self
    {
        $this->response = $response;

        return $this;
    }

    public function getIntentRaw(): ?string
    {
        return $this->intentRaw;
    }

    public function setIntentRaw(?string $intentRaw): self
    {
        $this->intentRaw = $intentRaw;

        return $this;
    }

    public function getIntent(): ?string
    {
        return $this->intent;
    }

    public function setIntent(?string $intent): self
    {
        $this->intent = $intent;

        return $this;
    }

    public function getKnowledgeKeys(): ?array
    {
        return $this->knowledgeKeys;
    }

    public function setKnowledgeKeys(?array $knowledgeKeys): self
    {
        $this->knowledgeKeys = $knowledgeKeys;

        return $this;
    }

    public function getKnowledgeKeysValidated(): ?array
    {
        return $this->knowledgeKeysValidated;
    }

    public function setKnowledgeKeysValidated(?array $knowledgeKeysValidated): self
    {
        $this->knowledgeKeysValidated = $knowledgeKeysValidated;

        return $this;
    }

    public function getConstraints(): ?array
    {
        return $this->constraints;
    }

    public function setConstraints(?array $constraints): self
    {
        $this->constraints = $constraints;

        return $this;
    }
}
