<?php

namespace App\Service\Moon\Horizons;

use App\Entity\MoonEphemerisHour;
use App\Entity\MoonNasaImport;
use App\Repository\MoonEphemerisHourRepository;
use App\Repository\MoonNasaImportRepository;
use Doctrine\ORM\EntityManagerInterface;

final class MoonHorizonsImportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MoonNasaImportRepository $runRepository,
        private MoonEphemerisHourRepository $hourRepository,
        private MoonHorizonsRowMapperService $rowMapper,
    ) {
    }

    public function findRunById(int $runId): ?MoonNasaImport
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
        bool $storeOnly,
        \DateTimeZone $utc
    ): MoonNasaImport {
        $run = new MoonNasaImport();
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
        $run->setRetrievedAtUtc(new \DateTime('now', $utc));
        $run->setStatus($storeOnly ? 'downloaded' : 'running');

        $this->entityManager->persist($run);
        $this->entityManager->flush();

        return $run;
    }

    /**
     * @return array{saved:int, updated:int}
     */
    public function importParsedRows(MoonNasaImport $run, MoonHorizonsParseResult $parseResult, \DateTimeZone $utc): array
    {
        $createdAt = new \DateTime('now', $utc);
        $saved = 0;
        $updated = 0;
        $start = $run->getStartUtc();
        $stop = $run->getStopUtc();
        if ($start instanceof \DateTimeInterface && $stop instanceof \DateTimeInterface) {
            $existingRows = $this->hourRepository->findByTimestampRangeIndexed($start, $stop);
        } else {
            $existingRows = $this->hourRepository->findByRunIndexedByTimestamp($run);
        }
        $columnMap = $parseResult->getColumnMap();

        try {
            foreach ($parseResult->getRows() as $row) {
                $timestamp = $this->rowMapper->parseTimestamp($row, $columnMap, $utc);
                if (!$timestamp) {
                    continue;
                }

                $timestampKey = $timestamp->format('Y-m-d H:i');
                if (isset($existingRows[$timestampKey])) {
                    $hour = $existingRows[$timestampKey];
                    $hour->setRunId($run);
                    $updated++;
                } else {
                    $hour = new MoonEphemerisHour();
                    $hour->setRunId($run);
                    $hour->setTsUtc(clone $timestamp);
                    $saved++;
                }

                $this->rowMapper->hydrateHour($hour, $row, $columnMap, $createdAt);
                $this->entityManager->persist($hour);
                $existingRows[$timestampKey] = $hour;
            }

            $run->setStatus('parsed');
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $run->setStatus('error');
            $run->setErrorMessage($e->getMessage());
            $this->entityManager->flush();

            throw $e;
        }

        return [
            'saved' => $saved,
            'updated' => $updated,
        ];
    }
}
