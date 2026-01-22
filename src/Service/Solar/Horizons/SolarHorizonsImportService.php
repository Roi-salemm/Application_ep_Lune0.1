<?php

namespace App\Service\Solar\Horizons;

use App\Entity\SolarEphemerisHour;
use App\Repository\SolarEphemerisHourRepository;
use App\Service\Moon\Horizons\MoonHorizonsParseResult;
use Doctrine\ORM\EntityManagerInterface;

final class SolarHorizonsImportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SolarEphemerisHourRepository $hourRepository,
        private SolarHorizonsRowMapperService $rowMapper,
    ) {
    }

    /**
     * @return array{saved:int, updated:int}
     */
    public function importParsedRows(
        MoonHorizonsParseResult $parseResult,
        \DateTimeZone $utc,
        \DateTimeInterface $start,
        \DateTimeInterface $stop
    ): array {
        $createdAt = new \DateTime('now', $utc);
        $saved = 0;
        $updated = 0;
        $existingRows = $this->hourRepository->findByTimestampRangeIndexed($start, $stop);
        $columnMap = $parseResult->getColumnMap();

        foreach ($parseResult->getRows() as $row) {
            $timestamp = $this->rowMapper->parseTimestamp($row, $columnMap, $utc);
            if (!$timestamp) {
                continue;
            }

            $timestampKey = $timestamp->format('Y-m-d H:i');
            if (isset($existingRows[$timestampKey])) {
                $hour = $existingRows[$timestampKey];
                $updated++;
            } else {
                $hour = new SolarEphemerisHour();
                $hour->setTsUtc(clone $timestamp);
                $saved++;
            }

            $this->rowMapper->hydrateHour($hour, $row, $columnMap, $createdAt);
            $this->entityManager->persist($hour);
            $existingRows[$timestampKey] = $hour;
        }

        $this->entityManager->flush();

        return [
            'saved' => $saved,
            'updated' => $updated,
        ];
    }
}
