<?php

namespace App\Command;

use App\Entity\MoonEphemerisHour;
use App\Entity\MoonNasaImport;
use App\Repository\MoonEphemerisHourRepository;
use App\Repository\MoonNasaImportRepository;
use App\Service\Moon\Horizons\MoonHorizonsParserService;
use App\Service\Moon\Horizons\MoonHorizonsRowMapperService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:moon:parse-raw',
    description: 'Parse raw_response from moon_nasa_import and report raw values into moon_ephemeris_hour (no calculations).',
)]
class ParseMoonRawCommand extends Command
{
    public function __construct(
        private MoonNasaImportRepository $runRepository,
        private MoonEphemerisHourRepository $hourRepository,
        private MoonHorizonsParserService $parserService,
        private MoonHorizonsRowMapperService $rowMapper,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('run-id', null, InputOption::VALUE_OPTIONAL, 'Run id to parse (default: latest)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $utc = new \DateTimeZone('UTC');
        $runIdOption = $input->getOption('run-id');

        $run = $runIdOption !== null
            ? $this->runRepository->find((int) $runIdOption)
            : $this->findLatestRun();

        if (!$run) {
            $output->writeln('<error>Run introuvable.</error>');
            return Command::FAILURE;
        }

        $rawResponse = $run->getRawResponse();
        if (!$rawResponse) {
            $output->writeln('<error>Raw response vide pour ce run.</error>');
            return Command::FAILURE;
        }

        $parseResult = $this->parserService->parseResponse($rawResponse);
        $columnMap = $parseResult->getColumnMap();
        $headerMap = $this->buildHeaderMap($parseResult->getHeader());

        $existingRows = $this->findExistingRows($run);
        $createdAt = new \DateTime('now', $utc);
        $saved = 0;
        $updated = 0;
        $skipped = 0;
        $skippedMissingMoon = 0;
        $mode = $this->isSunRun($run) ? 'sun' : 'moon';

        try {
            foreach ($parseResult->getRows() as $row) {
                $timestamp = $this->rowMapper->parseTimestamp($row, $columnMap, $utc);
                if (!$timestamp) {
                    $skipped++;
                    continue;
                }

                $timestampKey = $timestamp->format('Y-m-d H:i');
                if (isset($existingRows[$timestampKey])) {
                    $hour = $existingRows[$timestampKey];
                    $updated++;
                } else {
                    if ($mode === 'sun') {
                        $skippedMissingMoon++;
                        continue;
                    }
                    $hour = new MoonEphemerisHour();
                    $hour->setTsUtc(\DateTime::createFromInterface($timestamp));
                    $saved++;
                }

                if ($mode === 'sun') {
                    $this->hydrateSunValues($hour, $row['cols'] ?? [], $columnMap);
                } else {
                    $hour->setRunId($run);
                    $hour->setRawLine($row['raw'] ?? null);
                    $hour->setRawData($this->buildRawData($row['cols'] ?? [], $headerMap));
                    $this->hydrateRawValues($hour, $row['cols'] ?? [], $columnMap);
                    $hour->setCreatedAtUtc(clone $createdAt);
                }

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

        $output->writeln(sprintf(
            'Run id: %d | mode: %s | saved: %d | updated: %d | skipped: %d | skipped_missing_moon: %d',
            $run->getId(),
            $mode,
            $saved,
            $updated,
            $skipped,
            $skippedMissingMoon
        ));

        return Command::SUCCESS;
    }

    /**
     * @return array<string, MoonEphemerisHour>
     */
    private function findExistingRows(MoonNasaImport $run): array
    {
        $start = $run->getStartUtc();
        $stop = $run->getStopUtc();

        if ($start instanceof \DateTimeInterface && $stop instanceof \DateTimeInterface) {
            return $this->hourRepository->findByTimestampRangeIndexed($start, $stop);
        }

        return $this->hourRepository->findByRunIndexedByTimestamp($run);
    }

    private function findLatestRun(): ?MoonNasaImport
    {
        return $this->runRepository->createQueryBuilder('m')
            ->orderBy('m.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function isSunRun(MoonNasaImport $run): bool
    {
        $target = trim((string) $run->getTarget());
        if ($target === '') {
            return false;
        }

        if ($target === '10') {
            return true;
        }

        return stripos($target, 'sun') !== false;
    }

    /**
     * @param array<int, string>|null $header
     * @return array<int, string>
     */
    private function buildHeaderMap(?array $header): array
    {
        $map = [];
        $seen = [];

        foreach ($header ?? [] as $index => $label) {
            $clean = trim((string) $label);
            if ($clean === '') {
                $clean = sprintf('col_%02d', $index);
            }

            $base = $clean;
            $suffix = 2;
            while (isset($seen[$clean])) {
                $clean = $base . '_' . $suffix;
                $suffix++;
            }

            $seen[$clean] = true;
            $map[$index] = $clean;
        }

        return $map;
    }

    /**
     * @param array<int, string> $cols
     * @param array<int, string> $headerMap
     * @return array<string, string|null>
     */
    private function buildRawData(array $cols, array $headerMap): array
    {
        $data = [];
        foreach ($cols as $index => $value) {
            $key = $headerMap[$index] ?? sprintf('col_%02d', $index);
            $data[$key] = $value;
        }

        return $data;
    }

    /**
     * @param array<int, string> $cols
     * @param array<string, int|null> $columnMap
     */
    private function hydrateRawValues(MoonEphemerisHour $hour, array $cols, array $columnMap): void
    {
        $phaseDeg = $this->parseDecimal($this->extractColumnValue($cols, $columnMap['phase_deg'] ?? null));
        $sunTargetObs = $this->parseDecimal($this->extractColumnValue($cols, $columnMap['sun_target_obs_deg'] ?? null));
        if ($phaseDeg === null && $sunTargetObs !== null) {
            $phaseDeg = $sunTargetObs;
        }

        $hour->setPhaseDeg($phaseDeg);
        $hour->setIllumPct($this->parseDecimal($this->extractColumnValue($cols, $columnMap['illum_pct'] ?? null)));
        $hour->setAgeDays($this->parseDecimal($this->extractColumnValue($cols, $columnMap['age_days'] ?? null)));
        $hour->setDiamKm($this->parseDecimal($this->extractColumnValue($cols, $columnMap['diam_km'] ?? null)));
        $hour->setDistKm($this->parseDecimal($this->extractColumnValue($cols, $columnMap['dist_km'] ?? null)));
        $hour->setRaHours($this->parseRaHours($this->extractColumnValue($cols, $columnMap['ra_hours'] ?? null)));
        $hour->setDecDeg($this->parseDecDegrees($this->extractColumnValue($cols, $columnMap['dec_deg'] ?? null)));
        $hour->setSlonDeg($this->parseDecimal($this->extractColumnValue($cols, $columnMap['slon_deg'] ?? null)));
        $hour->setSlatDeg($this->parseDecimal($this->extractColumnValue($cols, $columnMap['slat_deg'] ?? null)));
        $hour->setSubObsLonDeg($this->parseDecimal($this->extractColumnValue($cols, $columnMap['sub_obs_lon_deg'] ?? null)));
        $hour->setSubObsLatDeg($this->parseDecimal($this->extractColumnValue($cols, $columnMap['sub_obs_lat_deg'] ?? null)));
        $hour->setElonDeg($this->parseDecimal($this->extractColumnValue($cols, $columnMap['elon_deg'] ?? null)));
        $hour->setElatDeg($this->parseDecimal($this->extractColumnValue($cols, $columnMap['elat_deg'] ?? null)));
        $hour->setAxisADeg($this->parseDecimal($this->extractColumnValue($cols, $columnMap['axis_a_deg'] ?? null)));
        $hour->setDeltaAu($this->parseDecimal($this->extractColumnValue($cols, $columnMap['delta_au'] ?? null)));
        $hour->setDeldotKmS($this->parseDecimal($this->extractColumnValue($cols, $columnMap['deldot_km_s'] ?? null)));
        $hour->setSunElongDeg($this->parseDecimal($this->extractColumnValue($cols, $columnMap['sun_elong_deg'] ?? null)));
        $hour->setSunTrail($this->parseText($this->extractColumnValue($cols, $columnMap['sun_trail'] ?? null)));
        $hour->setSunTargetObsDeg($sunTargetObs);
        $hour->setConstellation($this->parseText($this->extractColumnValue($cols, $columnMap['constellation'] ?? null)));
        $hour->setDeltaTSec($this->parseDecimal($this->extractColumnValue($cols, $columnMap['delta_t_sec'] ?? null)));
        $hour->setDut1Sec($this->parseDecimal($this->extractColumnValue($cols, $columnMap['dut1_sec'] ?? null)));
    }

    /**
     * @param array<int, string> $cols
     * @param array<string, int|null> $columnMap
     */
    private function hydrateSunValues(MoonEphemerisHour $hour, array $cols, array $columnMap): void
    {
        $hour->setSunRaHours($this->parseRaHours($this->extractColumnValue($cols, $columnMap['ra_hours'] ?? null)));
        $hour->setSunDecDeg($this->parseDecDegrees($this->extractColumnValue($cols, $columnMap['dec_deg'] ?? null)));
        $hour->setSunEclLonDeg($this->parseDecimal($this->extractColumnValue($cols, $columnMap['elon_deg'] ?? null)));
        $hour->setSunEclLatDeg($this->parseDecimal($this->extractColumnValue($cols, $columnMap['elat_deg'] ?? null)));

        $sunDist = $this->parseDecimal($this->extractColumnValue($cols, $columnMap['delta_au'] ?? null));
        if ($sunDist === null) {
            $sunDist = $this->parseDecimal($this->extractColumnValue($cols, $columnMap['dist_km'] ?? null));
        }
        $hour->setSunDistAu($sunDist);
    }

    private function extractColumnValue(array $cols, ?int $index): ?string
    {
        if ($index === null) {
            return null;
        }

        return $cols[$index] ?? null;
    }

    private function parseText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = trim($value);
        return $clean === '' ? null : $clean;
    }

    private function parseDecimal(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = trim($value);
        if ($clean === '' || strtolower($clean) === 'n.a.' || strtolower($clean) === 'na') {
            return null;
        }

        $clean = str_replace(['km', 'KM', 'deg', 'DEG', 'au', 'AU'], '', $clean);
        $clean = trim($clean, " \t\n\r\0\x0B\"'");
        $clean = trim($clean);

        if ($clean === '') {
            return null;
        }

        if (!preg_match('/^[+-]?\d+(\.\d+)?([Ee][+-]?\d+)?$/', $clean)) {
            return null;
        }

        return $clean;
    }

    private function parseRaHours(?string $value): ?string
    {
        $decimal = $this->parseDecimal($value);
        if ($decimal !== null) {
            return $decimal;
        }

        $hours = $this->parseHmsToDecimal($value);
        return $this->formatDecimal($hours, 10);
    }

    private function parseDecDegrees(?string $value): ?string
    {
        $decimal = $this->parseDecimal($value);
        if ($decimal !== null) {
            return $decimal;
        }

        $degrees = $this->parseDmsToDecimal($value);
        return $this->formatDecimal($degrees, 10);
    }

    private function parseHmsToDecimal(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $clean = trim($value);
        if ($clean === '') {
            return null;
        }

        $clean = str_replace(['h', 'm', 's'], ' ', $clean);
        $parts = preg_split('/[:\s]+/', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (!$parts) {
            return null;
        }

        $hours = (float) $parts[0];
        $minutes = isset($parts[1]) ? (float) $parts[1] : 0.0;
        $seconds = isset($parts[2]) ? (float) $parts[2] : 0.0;

        return $hours + ($minutes / 60.0) + ($seconds / 3600.0);
    }

    private function parseDmsToDecimal(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $clean = trim($value);
        if ($clean === '') {
            return null;
        }

        $clean = str_replace(['d', "'", '"'], ' ', $clean);
        $parts = preg_split('/[:\s]+/', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (!$parts) {
            return null;
        }

        $sign = 1.0;
        if (str_starts_with($parts[0], '-')) {
            $sign = -1.0;
        }

        $degrees = abs((float) $parts[0]);
        $minutes = isset($parts[1]) ? (float) $parts[1] : 0.0;
        $seconds = isset($parts[2]) ? (float) $parts[2] : 0.0;

        return $sign * ($degrees + ($minutes / 60.0) + ($seconds / 3600.0));
    }

    private function formatDecimal(?float $value, int $scale): ?string
    {
        if ($value === null) {
            return null;
        }

        $formatted = sprintf('%.' . $scale . 'f', $value);
        return rtrim(rtrim($formatted, '0'), '.');
    }
}
