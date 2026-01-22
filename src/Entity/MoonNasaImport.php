<?php

namespace App\Entity;

use App\Repository\MoonNasaImportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MoonNasaImportRepository::class)]
class MoonNasaImport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $provider = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $target = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $center = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $year = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $start_utc = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $stop_utc = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $step_size = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $time_zone = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $sha256 = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $retrieved_at_utc = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error_message = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $raw_response = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(?string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function setTarget(?string $target): static
    {
        $this->target = $target;

        return $this;
    }

    public function getCenter(): ?string
    {
        return $this->center;
    }

    public function setCenter(?string $center): static
    {
        $this->center = $center;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(?int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getStartUtc(): ?\DateTime
    {
        return $this->start_utc;
    }

    public function setStartUtc(?\DateTime $start_utc): static
    {
        $this->start_utc = $start_utc;

        return $this;
    }

    public function getStopUtc(): ?\DateTime
    {
        return $this->stop_utc;
    }

    public function setStopUtc(?\DateTime $stop_utc): static
    {
        $this->stop_utc = $stop_utc;

        return $this;
    }

    public function getStepSize(): ?string
    {
        return $this->step_size;
    }

    public function setStepSize(?string $step_size): static
    {
        $this->step_size = $step_size;

        return $this;
    }

    public function getTimeZone(): ?string
    {
        return $this->time_zone;
    }

    public function setTimeZone(string $time_zone): static
    {
        $this->time_zone = $time_zone;

        return $this;
    }

    public function getSha256(): ?string
    {
        return $this->sha256;
    }

    public function setSha256(?string $sha256): static
    {
        $this->sha256 = $sha256;

        return $this;
    }

    public function getRetrievedAtUtc(): ?\DateTime
    {
        return $this->retrieved_at_utc;
    }

    public function setRetrievedAtUtc(?\DateTime $retrieved_at_utc): static
    {
        $this->retrieved_at_utc = $retrieved_at_utc;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->error_message;
    }

    public function setErrorMessage(?string $error_message): static
    {
        $this->error_message = $error_message;

        return $this;
    }

    public function getRawResponse(): ?string
    {
        return $this->raw_response;
    }

    public function setRawResponse(?string $raw_response): static
    {
        $this->raw_response = $raw_response;

        return $this;
    }
}
