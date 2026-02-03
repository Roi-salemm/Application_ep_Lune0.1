<?php

/**
 * Service de persistance pour import_horizon.
 * Pourquoi: centraliser la creation des runs avec raw_header et query_params.
 * Infos: ne fait aucun parsing, il stocke uniquement la reponse brute.
 */

namespace App\Service\Horizon;

use App\Entity\ImportHorizon;
use App\Repository\ImportHorizonRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ImportHorizonService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ImportHorizonRepository $runRepository,
    ) {
    }

    public function findRunById(int $runId): ?ImportHorizon
    {
        return $this->runRepository->find($runId);
    }

    public function createRun(
        string $target,
        string $center,
        \DateTimeInterface $start,
        \DateTimeInterface $stop,
        string $step,
        string $timeZoneLabel,
        string $rawResponse,
        ?string $rawHeader,
        array $queryParams,
        \DateTimeZone $utc
    ): ImportHorizon {
        $run = new ImportHorizon();
        $run->setProvider('nasa-horizons');
        $run->setTarget($target);
        $run->setCenter($center);
        $run->setYear((int) $start->format('Y'));
        $run->setStartUtc(\DateTime::createFromInterface($start));
        $run->setStopUtc(\DateTime::createFromInterface($stop));
        $run->setStepSize($step);
        $run->setTimeZone($timeZoneLabel);
        $run->setSha256(hash('sha256', $rawResponse));
        $run->setRawResponse($rawResponse);
        $run->setRawHeader($rawHeader);
        $run->setQueryParams($queryParams);
        $run->setRetrievedAtUtc(new \DateTime('now', $utc));
        $run->setStatus('downloaded');

        $this->entityManager->persist($run);
        $this->entityManager->flush();

        return $run;
    }
}
