<?php

namespace App\Command;

use App\Entity\MoonEphemerisHour;
use App\Entity\MoonNasaImport;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;


//& Infos utilie 
//^^ la commande :
// php bin/console app:moon:import-horizons --start="2026-01-21 00:00" --days=7 --step=1h


#[AsCommand(
    name: 'app:moon:import-horizons',
    description: 'Import Moon ephemeris data from NASA JPL Horizons.',
)]
class ImportMoonHorizonsCommand extends Command
{
    private const HORIZONS_ENDPOINT = 'https://ssd.jpl.nasa.gov/api/horizons.api';
    private const AU_TO_KM = 149597870.7;
    private const SYNODIC_MONTH_DAYS = 29.530588;
    private const MOON_RADIUS_KM = 1737.4;

    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('start', null, InputOption::VALUE_OPTIONAL, 'Start time (UTC). Example: 2026-01-01 00:00', 'now')
            ->addOption('stop', null, InputOption::VALUE_OPTIONAL, 'Stop time (UTC). Example: 2026-01-08 00:00')
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Days to import when stop is not provided', '7')
            ->addOption('step', null, InputOption::VALUE_OPTIONAL, 'Horizons step size', '1h')
            ->addOption('target', null, InputOption::VALUE_OPTIONAL, 'Target body (301 = Moon)', '301')
            ->addOption('center', null, InputOption::VALUE_OPTIONAL, 'Center body or site', '500@399')
            ->addOption('quantities', null, InputOption::VALUE_OPTIONAL, 'Horizons quantities list', '1,20,23,24,29')
            ->addOption('time-zone', null, InputOption::VALUE_OPTIONAL, 'Time zone label stored in the run', 'UTC')
            ->addOption('show-columns', null, InputOption::VALUE_NONE, 'Print parsed column mapping')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Fetch and parse without saving to the database')
            ->addOption('store-only', null, InputOption::VALUE_NONE, 'Store the raw response only')
            ->addOption('run-id', null, InputOption::VALUE_OPTIONAL, 'Parse an existing import run by id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $utc = new \DateTimeZone('UTC');
        $dryRun = (bool) $input->getOption('dry-run');
        $storeOnly = (bool) $input->getOption('store-only');
        $runIdOption = $input->getOption('run-id');

        $run = null;
        $body = null;
        $start = null;
        $stop = null;
        $target = null;
        $center = null;
        $step = null;
        $quantities = null;
        $timeZoneLabel = null;

        if ($runIdOption !== null) {
            $run = $this->entityManager->getRepository(MoonNasaImport::class)->find((int) $runIdOption);
            if (!$run) {
                $output->writeln('<error>Run id not found.</error>');
                return Command::FAILURE;
            }

            $body = $run->getRawResponse();
            if (!$body) {
                $output->writeln('<error>Selected run has no raw response.</error>');
                return Command::FAILURE;
            }

            $start = $run->getStartUtc() ?? new \DateTime('now', $utc);
            $stop = $run->getStopUtc() ?? (clone $start);
            $output->writeln(sprintf('Parsing stored response for run id %d...', $run->getId()));
        } else {
            $start = $this->parseDateTimeInput((string) $input->getOption('start'), $utc);
            if (!$start) {
                $output->writeln('<error>Invalid --start value.</error>');
                return Command::FAILURE;
            }

            $stopOption = $input->getOption('stop');
            if ($stopOption !== null) {
                $stop = $this->parseDateTimeInput((string) $stopOption, $utc);
                if (!$stop) {
                    $output->writeln('<error>Invalid --stop value.</error>');
                    return Command::FAILURE;
                }
            } else {
                $days = max(1, (int) $input->getOption('days'));
                $stop = (clone $start)->modify(sprintf('+%d days', $days));
            }

            if ($stop <= $start) {
                $output->writeln('<error>Stop time must be after start time.</error>');
                return Command::FAILURE;
            }

            $target = trim((string) $input->getOption('target'));
            $center = trim((string) $input->getOption('center'));
            $step = trim((string) $input->getOption('step'));
            $quantities = trim((string) $input->getOption('quantities'));
            $timeZoneLabel = trim((string) $input->getOption('time-zone')) ?: 'UTC';

            $query = [
                'format' => 'text',
                'COMMAND' => $this->quoteParam($target),
                'CENTER' => $this->quoteParam($center),
                'MAKE_EPHEM' => $this->quoteParam('YES'),
                'EPHEM_TYPE' => $this->quoteParam('OBSERVER'),
                'START_TIME' => $this->quoteParam($start->format('Y-m-d H:i')),
                'STOP_TIME' => $this->quoteParam($stop->format('Y-m-d H:i')),
                'STEP_SIZE' => $this->quoteParam($step),
                'QUANTITIES' => $this->quoteParam($quantities),
                'CSV_FORMAT' => $this->quoteParam('YES'),
                'OUT_UNITS' => $this->quoteParam('KM-S'),
            ];

            $output->writeln('Requesting ephemeris from NASA JPL Horizons...');

            try {
                $response = $this->httpClient->request('GET', self::HORIZONS_ENDPOINT, [
                    'query' => $query,
                ]);
                $status = $response->getStatusCode();
                $body = $response->getContent(false);
            } catch (\Throwable $e) {
                $output->writeln('<error>HTTP request failed: ' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }

            if ($status >= 400) {
                $output->writeln('<error>Horizons returned HTTP ' . $status . '.</error>');
                return Command::FAILURE;
            }
        }

        [$header, $rows, $headerLine] = $this->extractCsvRows($body);
        $columnMap = $this->buildColumnMap($header);
        if ($input->getOption('show-columns')) {
            if ($header) {
                $output->writeln('Columns: ' . implode(' | ', $header));
            } elseif ($rows) {
                $output->writeln('Columns: <no header found>');
                $output->writeln('First data row: ' . implode(' | ', $rows[0]['cols']));
            } else {
                $output->writeln('Columns: <no header or data found>');
            }

            if ($headerLine && !$header) {
                $output->writeln('Header candidate: ' . $headerLine);
            }

            $output->writeln('Mapping: ' . $this->formatColumnMap($columnMap));
        }

        if ($dryRun) {
            $output->writeln(sprintf('Parsed rows: %d', count($rows)));
            return Command::SUCCESS;
        }

        if ($run === null) {
            $run = new MoonNasaImport();
            $run->setProvider('nasa-horizons');
            $run->setTarget($target);
            $run->setCenter($center);
            $run->setYear((int) $start->format('Y'));
            $run->setStartUtc(clone $start);
            $run->setStopUtc(clone $stop);
            $run->setStepSize($step);
            $run->setTimeZone($timeZoneLabel);
            $run->setSha256(hash('sha256', $body));
            $run->setRawResponse($body);
            $run->setRetrievedAtUtc(new \DateTime('now', $utc));
            $run->setStatus($storeOnly ? 'downloaded' : 'running');

            $this->entityManager->persist($run);
            $this->entityManager->flush();

            if ($storeOnly) {
                $output->writeln(sprintf('Saved run id: %d.', $run->getId()));
                return Command::SUCCESS;
            }
        }

        $createdAt = new \DateTime('now', $utc);
        $saved = 0;
        $updated = 0;
        $existingRows = $this->loadExistingRows($run);
        try {
            foreach ($rows as $row) {
                $timestampValue = $this->extractColumnValue($row['cols'], $columnMap['timestamp'] ?? null);
                if ($timestampValue === null && $row['cols']) {
                    $timestampValue = $row['cols'][0];
                }
                $timestamp = $this->parseHorizonsTimestamp($timestampValue, $utc);
                if (!$timestamp) {
                    continue;
                }
                $timestampKey = $timestamp->format('Y-m-d H:i');
                if (isset($existingRows[$timestampKey])) {
                    $hour = $existingRows[$timestampKey];
                    $updated++;
                } else {
                    $hour = new MoonEphemerisHour();
                    $hour->setRunId($run);
                    $hour->setTsUtc($timestamp);
                    $saved++;
                }
                $phaseDeg = $this->parseDecimal($this->extractColumnValue($row['cols'], $columnMap['phase_deg'] ?? null));
                $ageDays = $this->parseDecimal($this->extractColumnValue($row['cols'], $columnMap['age_days'] ?? null));
                $diamValue = $this->parseDecimal($this->extractColumnValue($row['cols'], $columnMap['diam_km'] ?? null));
                $distKmValue = $this->parseDecimal($this->extractColumnValue($row['cols'], $columnMap['dist_km'] ?? null));
                $deltaAu = $this->parseDecimal($this->extractColumnValue($row['cols'], $columnMap['delta_au'] ?? null));
                $deldotKmS = $this->parseDecimal($this->extractColumnValue($row['cols'], $columnMap['deldot_km_s'] ?? null));
                $sunElong = $this->parseDecimal($this->extractColumnValue($row['cols'], $columnMap['sun_elong_deg'] ?? null));
                $sunTrail = $this->parseText($this->extractColumnValue($row['cols'], $columnMap['sun_trail'] ?? null));
                $sunTargetObs = $this->parseDecimal($this->extractColumnValue($row['cols'], $columnMap['sun_target_obs_deg'] ?? null));
                $constellation = $this->parseText($this->extractColumnValue($row['cols'], $columnMap['constellation'] ?? null));

                if ($phaseDeg === null) {
                    $phaseDeg = $sunTargetObs;
                }

                $distKm = $distKmValue ?? $this->parseDistanceKm($deltaAu);

                $hour->setPhaseDeg($phaseDeg);
                $hour->setAgeDays($ageDays ?? $this->computeAgeDaysFromPhase($phaseDeg));
                $hour->setDiamKm($diamValue ?? $this->computeAngularDiameterArcsec($distKm));
                $hour->setDistKm($distKm);
                $hour->setRaHours($this->parseRaHours($this->extractColumnValue($row['cols'], $columnMap['ra_hours'] ?? null)));
                $hour->setDecDeg($this->parseDecDegrees($this->extractColumnValue($row['cols'], $columnMap['dec_deg'] ?? null)));
                $hour->setSlonDeg($this->parseDecimal($this->extractColumnValue($row['cols'], $columnMap['slon_deg'] ?? null)));
                $hour->setSlatDeg($this->parseDecimal($this->extractColumnValue($row['cols'], $columnMap['slat_deg'] ?? null)));
                $hour->setElonDeg($this->parseDecimal($this->extractColumnValue($row['cols'], $columnMap['elon_deg'] ?? null)));
                $hour->setElatDeg($this->parseDecimal($this->extractColumnValue($row['cols'], $columnMap['elat_deg'] ?? null)));
                $hour->setAxisADeg($this->parseDecimal($this->extractColumnValue($row['cols'], $columnMap['axis_a_deg'] ?? null)));
                $hour->setDeltaAu($deltaAu);
                $hour->setDeldotKmS($deldotKmS);
                $hour->setSunElongDeg($sunElong);
                $hour->setSunTrail($sunTrail);
                $hour->setSunTargetObsDeg($sunTargetObs);
                $hour->setConstellation($constellation);
                $hour->setRawLine($row['raw']);
                $hour->setCreatedAtUtc(clone $createdAt);

                $this->entityManager->persist($hour);
                $existingRows[$timestampKey] = $hour;
            }

            $run->setStatus('parsed');
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $run->setStatus('error');
            $run->setErrorMessage($e->getMessage());
            $this->entityManager->flush();

            $output->writeln('<error>Import failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln(sprintf('Saved %d rows, updated %d (run id: %d).', $saved, $updated, $run->getId()));

        return Command::SUCCESS;
    }

    private function parseDateTimeInput(string $input, \DateTimeZone $tz): ?\DateTime
    {
        $value = trim($input);
        if ($value === '' || strtolower($value) === 'now') {
            return new \DateTime('now', $tz);
        }

        $formats = [
            'Y-m-d H:i',
            'Y-m-d H:i:s',
            'Y-m-d',
            \DateTimeInterface::ATOM,
            \DateTimeInterface::RFC3339,
        ];

        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $value, $tz);
            if ($parsed instanceof \DateTime) {
                return $parsed;
            }
        }

        try {
            return new \DateTime($value, $tz);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseHorizonsTimestamp(?string $value, \DateTimeZone $tz): ?\DateTime
    {
        if ($value === null) {
            return null;
        }

        $clean = trim(str_replace('A.D.', '', $value));
        $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;
        $clean = trim(str_replace('UT', '', $clean));

        if ($clean === '') {
            return null;
        }

        $formats = [
            'Y-M-d H:i',
            'Y-M-d H:i:s',
            'Y-M-d H:i:s.u',
            'Y-M-d H:i:s.v',
            'Y-m-d H:i',
            'Y-m-d H:i:s',
        ];

        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $clean, $tz);
            if ($parsed instanceof \DateTime) {
                return $parsed;
            }
        }

        try {
            return new \DateTime($clean, $tz);
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractCsvRows(string $body): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $body) ?: [];
        $inData = false;
        $headerLine = $this->findHeaderLine($lines);
        $header = $headerLine ? str_getcsv($headerLine) : null;
        $rows = [];

        foreach ($lines as $line) {
            if (str_contains($line, '$$SOE')) {
                $inData = true;
                continue;
            }

            if (str_contains($line, '$$EOE')) {
                break;
            }

            if (!$inData) {
                continue;
            }

            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $rows[] = [
                'raw' => $trimmed,
                'cols' => str_getcsv($trimmed),
            ];
        }

        return [$header, $rows, $headerLine];
    }

    private function buildColumnMap(?array $header): array
    {
        if (!$header) {
            return [];
        }

        return [
            'timestamp' => $this->findColumnIndex($header, ['~Date__\(UT\)__HR:MN~i', '~Date__\(UT\)~i', '~\bDATE\b~i', '~\bTIME\b~i', '~DATE__~i']),
            'ra_hours' => $this->findColumnIndex($header, ['~R\.?A\._\(ICRF\)~i', '~\bR\.?A\.?\b~i', '~RIGHT\s*ASCENSION~i']),
            'dec_deg' => $this->findColumnIndex($header, ['~DEC__\(ICRF\)~i', '~\bDEC\b~i', '~DECLINATION~i']),
            'dist_km' => $this->findColumnIndex($header, ['~\bRANGE\b~i', '~DIST~i']),
            'delta_au' => $this->findColumnIndex($header, ['~\bdelta\b~i']),
            'deldot_km_s' => $this->findColumnIndex($header, ['~deldot~i']),
            'sun_elong_deg' => $this->findColumnIndex($header, ['~S-O-T~i']),
            'sun_trail' => $this->findColumnIndex($header, ['~/r~i']),
            'sun_target_obs_deg' => $this->findColumnIndex($header, ['~S-T-O~i']),
            'constellation' => $this->findColumnIndex($header, ['~Cnst~i', '~Constell~i']),
            'diam_km' => $this->findColumnIndex($header, ['~ANG[-_\s]*DIAM~i', '~\bDIAM\b~i']),
            'phase_deg' => $this->findColumnIndex($header, ['~PHASE~i', '~PHAS[-_\s]*ANG~i']),
            'age_days' => $this->findColumnIndex($header, ['~LUNAR\s*AGE~i', '~\bAGE\b~i']),
            'slon_deg' => $this->findColumnIndex($header, ['~SUB[-_\s]*SOLAR.*LON~i', '~\bS[-_\s]*LON\b~i']),
            'slat_deg' => $this->findColumnIndex($header, ['~SUB[-_\s]*SOLAR.*LAT~i', '~\bS[-_\s]*LAT\b~i']),
            'elon_deg' => $this->findColumnIndex($header, ['~ECL[-_\s]*LON~i', '~ECLIPTIC.*LON~i']),
            'elat_deg' => $this->findColumnIndex($header, ['~ECL[-_\s]*LAT~i', '~ECLIPTIC.*LAT~i']),
            'axis_a_deg' => $this->findColumnIndex($header, ['~NP\.?ANG~i', '~P\.?A\.?~i', '~AXIS~i']),
        ];
    }

    private function findColumnIndex(array $header, array $patterns): ?int
    {
        foreach ($header as $index => $label) {
            $labelText = trim((string) $label);
            if ($labelText === '') {
                continue;
            }
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $labelText) === 1) {
                    return $index;
                }
            }
        }

        return null;
    }

    private function extractColumnValue(array $cols, ?int $index): ?string
    {
        if ($index === null) {
            return null;
        }

        return $cols[$index] ?? null;
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

        $clean = str_replace(['km', 'KM', 'deg', 'DEG'], '', $clean);
        $clean = trim($clean);

        if ($clean === '') {
            return null;
        }

        if (!preg_match('/^[+-]?\d+(\.\d+)?([Ee][+-]?\d+)?$/', $clean)) {
            return null;
        }

        return $clean;
    }

    private function parseText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = trim($value);
        return $clean === '' ? null : $clean;
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

    private function parseDistanceKm(?string $value): ?string
    {
        $decimal = $this->parseDecimal($value);
        if ($decimal === null) {
            return null;
        }

        $numeric = (float) $decimal;
        if ($numeric > 0 && $numeric < 10) {
            $numeric *= self::AU_TO_KM;
        }

        return $this->formatDecimal($numeric, 6);
    }

    private function computeAgeDaysFromPhase(?string $phaseDeg): ?string
    {
        if ($phaseDeg === null) {
            return null;
        }

        $numeric = (float) $phaseDeg;
        if ($numeric < 0) {
            return null;
        }

        $age = ($numeric / 360.0) * self::SYNODIC_MONTH_DAYS;
        return $this->formatDecimal($age, 6);
    }

    private function computeAngularDiameterArcsec(?string $distKm): ?string
    {
        if ($distKm === null) {
            return null;
        }

        $distance = (float) $distKm;
        if ($distance <= 0) {
            return null;
        }

        $angleRad = 2.0 * atan(self::MOON_RADIUS_KM / $distance);
        $angleDeg = rad2deg($angleRad);
        $arcsec = $angleDeg * 3600.0;

        return $this->formatDecimal($arcsec, 6);
    }

    private function formatDecimal(?float $value, int $scale): ?string
    {
        if ($value === null) {
            return null;
        }

        $formatted = sprintf('%.' . $scale . 'f', $value);
        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function loadExistingRows(MoonNasaImport $run): array
    {
        $rows = $this->entityManager->getRepository(MoonEphemerisHour::class)->findBy([
            'run_id' => $run,
        ]);

        $existing = [];
        foreach ($rows as $row) {
            $ts = $row->getTsUtc();
            if (!$ts instanceof \DateTimeInterface) {
                continue;
            }
            $existing[$ts->format('Y-m-d H:i')] = $row;
        }

        return $existing;
    }

    private function quoteParam(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return "''";
        }

        if (str_starts_with($trimmed, "'") && str_ends_with($trimmed, "'")) {
            return $trimmed;
        }

        return "'" . $trimmed . "'";
    }

    private function formatColumnMap(array $map): string
    {
        $pairs = [];
        foreach ($map as $key => $index) {
            if ($index !== null) {
                $pairs[] = $key . '=' . $index;
            }
        }

        return $pairs ? implode(', ', $pairs) : 'no columns matched';
    }

    private function findHeaderLine(array $lines): ?string
    {
        $candidate = null;

        foreach ($lines as $line) {
            if (str_contains($line, '$$SOE')) {
                break;
            }

            $trimmed = trim($line);
            if ($trimmed === '' || str_contains($trimmed, '$$')) {
                continue;
            }

            if (!str_contains($trimmed, ',')) {
                continue;
            }

            if (preg_match('/Date__\(UT\)|Date__\(UT\)__HR|R\.?A\._\(ICRF\)|DEC__\(ICRF\)|S-O-T|S-T-O/i', $trimmed) === 1) {
                $candidate = $trimmed;
            }
        }

        return $candidate;
    }
}












// API VERSION: 1.2
// API SOURCE: NASA/JPL Horizons API

// *******************************************************************************
//  Revised: July 31, 2013             Moon / (Earth)                          301
 
//  GEOPHYSICAL DATA (updated 2018-Aug-15):
//   Vol. mean radius, km  = 1737.53+-0.03    Mass, x10^22 kg       =    7.349
//   Radius (gravity), km  = 1738.0           Surface emissivity    =    0.92
//   Radius (IAU), km      = 1737.4           GM, km^3/s^2          = 4902.800066
//   Density, g/cm^3       =    3.3437        GM 1-sigma, km^3/s^2  =  +-0.0001  
//   V(1,0)                =   +0.21          Surface accel., m/s^2 =    1.62
//   Earth/Moon mass ratio = 81.3005690769    Farside crust. thick. = ~80 - 90 km
//   Mean crustal density  = 2.97+-.07 g/cm^3 Nearside crust. thick.= 58+-8 km 
//   Heat flow, Apollo 15  = 3.1+-.6 mW/m^2   Mean angular diameter = 31'05.2"
//   Heat flow, Apollo 17  = 2.2+-.5 mW/m^2   Sid. rot. rate, rad/s = 0.0000026617
//   Geometric Albedo      = 0.12             Mean solar day        = 29.5306 d
//   Obliquity to orbit    = 6.67 deg         Orbit period          = 27.321582 d
//   Semi-major axis, a    = 384400 km        Eccentricity          = 0.05490
//   Mean motion, rad/s    = 2.6616995x10^-6  Inclination           = 5.145 deg
//   Apsidal period        = 3231.50 d        Nodal period          = 6798.38 d
//                                  Perihelion  Aphelion    Mean
//   Solar Constant (W/m^2)         1414+-7     1323+-7     1368+-7
//   Maximum Planetary IR (W/m^2)   1314        1226        1268
//   Minimum Planetary IR (W/m^2)      5.2         5.2         5.2
// ********************************************************************************


// *******************************************************************************
// Ephemeris / API_USER Wed Jan 21 09:02:08 2026 Pasadena, USA      / Horizons    
// *******************************************************************************
// Target body name: Moon (301)                      {source: DE441}
// Center body name: Earth (399)                     {source: DE441}
// Center-site name: GEOCENTRIC
// *******************************************************************************
// Start time      : A.D. 2026-Jan-21 17:00:00.0000 UT      
// Stop  time      : A.D. 2026-Jan-28 17:00:00.0000 UT      
// Step-size       : 60 minutes
// *******************************************************************************
// Target pole/equ : MOON_ME                         {East-longitude positive}
// Target radii    : 1737.4, 1737.4, 1737.4 km       {Equator_a, b, pole_c}       
// Center geodetic : 0.0, 0.0, -6378.137             {E-lon(deg),Lat(deg),Alt(km)}
// Center cylindric: 0.0, 0.0, 0.0                   {E-lon(deg),Dxy(km),Dz(km)}
// Center pole/equ : ITRF93                          {East-longitude positive}
// Center radii    : 6378.137, 6378.137, 6356.752 km {Equator_a, b, pole_c}       
// Target primary  : Earth
// Vis. interferer : MOON (R_eq= 1737.400) km        {source: DE441}
// Rel. light bend : Sun                             {source: DE441}
// Rel. lght bnd GM: 1.3271E+11 km^3/s^2                                          
// Atmos refraction: NO (AIRLESS)
// RA format       : HMS
// Time format     : CAL 
// Calendar mode   : Mixed Julian/Gregorian
// EOP file        : eop.260120.p260418                                           
// EOP coverage    : DATA-BASED 1962-JAN-20 TO 2026-JAN-20. PREDICTS-> 2026-APR-17
// Units conversion: 1 au= 149597870.700 km, c= 299792.458 km/s, 1 day= 86400.0 s 
// Table cut-offs 1: Elevation (-90.0deg=NO ),Airmass (>38.000=NO), Daylight (NO )
// Table cut-offs 2: Solar elongation (  0.0,180.0=NO ),Local Hour Angle( 0.0=NO )
// Table cut-offs 3: RA/DEC angular rate (     0.0=NO )                           
// Table format    : Comma Separated Values (spreadsheet)
// ****************************************************************************************************************
//  Date__(UT)__HR:MN, , , R.A._(ICRF), DEC__(ICRF),             delta,     deldot,     S-O-T,/r,     S-T-O,  Cnst,
// ****************************************************************************************************************
// $$SOE
//  2026-Jan-21 17:00, , , 22 28 25.65, -09 56 44.1,  0.00257937606636, -0.0383063,   33.8469,/T,  146.0694,   Aqr,
//  2026-Jan-21 18:00, , , 22 30 24.41, -09 42 01.1,  0.00257845447909, -0.0382823,   34.3477,/T,  145.5676,   Aqr,
//  2026-Jan-21 19:00, , , 22 32 23.06, -09 27 15.0,  0.00257753347650, -0.0382577,   34.8488,/T,  145.0654,   Aqr,
//  2026-Jan-21 20:00, , , 22 34 21.61, -09 12 25.8,  0.00257661306985, -0.0382327,   35.3504,/T,  144.5628,   Aqr,
//  2026-Jan-21 21:00, , , 22 36 20.08, -08 57 33.6,  0.00257569326988, -0.0382072,   35.8524,/T,  144.0598,   Aqr,
//  2026-Jan-21 22:00, , , 22 38 18.46, -08 42 38.5,  0.00257477408707, -0.0381813,   36.3548,/T,  143.5564,   Aqr,
//  2026-Jan-21 23:00, , , 22 40 16.76, -08 27 40.6,  0.00257385553146, -0.0381550,   36.8575,/T,  143.0525,   Aqr,
//  2026-Jan-22 00:00, , , 22 42 14.97, -08 12 39.8,  0.00257293761281, -0.0381284,   37.3607,/T,  142.5483,   Aqr,
//  2026-Jan-22 01:00, , , 22 44 13.11, -07 57 36.3,  0.00257202034058, -0.0381013,   37.8643,/T,  142.0437,   Aqr,
//  2026-Jan-22 02:00, , , 22 46 11.19, -07 42 30.1,  0.00257110372391, -0.0380738,   38.3683,/T,  141.5387,   Aqr,
//  2026-Jan-22 03:00, , , 22 48 09.19, -07 27 21.3,  0.00257018777169, -0.0380460,   38.8727,/T,  141.0333,   Aqr,
//  2026-Jan-22 04:00, , , 22 50 07.13, -07 12 09.9,  0.00256927249256, -0.0380178,   39.3775,/T,  140.5275,   Aqr,
//  2026-Jan-22 05:00, , , 22 52 05.02, -06 56 56.1,  0.00256835789498, -0.0379893,   39.8827,/T,  140.0214,   Aqr,
//  2026-Jan-22 06:00, , , 22 54 02.85, -06 41 39.9,  0.00256744398713, -0.0379605,   40.3883,/T,  139.5148,   Aqr,
//  2026-Jan-22 07:00, , , 22 56 00.63, -06 26 21.4,  0.00256653077711, -0.0379313,   40.8943,/T,  139.0079,   Aqr,
//  2026-Jan-22 08:00, , , 22 57 58.37, -06 11 00.5,  0.00256561827278, -0.0379018,   41.4006,/T,  138.5006,   Aqr,
//  2026-Jan-22 09:00, , , 22 59 56.06, -05 55 37.5,  0.00256470648192, -0.0378719,   41.9074,/T,  137.9929,   Aqr,
//  2026-Jan-22 10:00, , , 23 01 53.72, -05 40 12.3,  0.00256379541222, -0.0378418,   42.4145,/T,  137.4848,   Aqr,
//  2026-Jan-22 11:00, , , 23 03 51.35, -05 24 45.0,  0.00256288507121, -0.0378113,   42.9220,/T,  136.9763,   Aqr,
//  2026-Jan-22 12:00, , , 23 05 48.95, -05 09 15.8,  0.00256197546644, -0.0377806,   43.4299,/T,  136.4675,   Aqr,
//  2026-Jan-22 13:00, , , 23 07 46.53, -04 53 44.5,  0.00256106660538, -0.0377495,   43.9382,/T,  135.9583,   Aqr,
//  2026-Jan-22 14:00, , , 23 09 44.09, -04 38 11.4,  0.00256015849549, -0.0377181,   44.4469,/T,  135.4487,   Aqr,
//  2026-Jan-22 15:00, , , 23 11 41.64, -04 22 36.5,  0.00255925114423, -0.0376864,   44.9559,/T,  134.9388,   Aqr,
//  2026-Jan-22 16:00, , , 23 13 39.18, -04 06 59.9,  0.00255834455915, -0.0376544,   45.4653,/T,  134.4285,   Aqr,
//  2026-Jan-22 17:00, , , 23 15 36.72, -03 51 21.5,  0.00255743874775, -0.0376220,   45.9751,/T,  133.9178,   Aqr,
//  2026-Jan-22 18:00, , , 23 17 34.26, -03 35 41.6,  0.00255653371770, -0.0375894,   46.4853,/T,  133.4067,   Aqr,
//  2026-Jan-22 19:00, , , 23 19 31.80, -03 20 00.1,  0.00255562947677, -0.0375564,   46.9958,/T,  132.8953,   Aqr,
//  2026-Jan-22 20:00, , , 23 21 29.35, -03 04 17.2,  0.00255472603281, -0.0375231,   47.5068,/T,  132.3835,   Psc,
//  2026-Jan-22 21:00, , , 23 23 26.92, -02 48 32.8,  0.00255382339383, -0.0374895,   48.0181,/T,  131.8714,   Psc,
//  2026-Jan-22 22:00, , , 23 25 24.51, -02 32 47.1,  0.00255292156805, -0.0374555,   48.5297,/T,  131.3589,   Psc,
//  2026-Jan-22 23:00, , , 23 27 22.13, -02 17 00.1,  0.00255202056388, -0.0374211,   49.0418,/T,  130.8460,   Psc,
//  2026-Jan-23 00:00, , , 23 29 19.77, -02 01 12.0,  0.00255112038991, -0.0373864,   49.5542,/T,  130.3327,   Psc,
//  2026-Jan-23 01:00, , , 23 31 17.45, -01 45 22.7,  0.00255022105505, -0.0373514,   50.0670,/T,  129.8191,   Psc,
//  2026-Jan-23 02:00, , , 23 33 15.17, -01 29 32.3,  0.00254932256841, -0.0373159,   50.5802,/T,  129.3051,   Psc,
//  2026-Jan-23 03:00, , , 23 35 12.93, -01 13 40.9,  0.00254842493944, -0.0372800,   51.0937,/T,  128.7908,   Psc,
//  2026-Jan-23 04:00, , , 23 37 10.74, -00 57 48.6,  0.00254752817791, -0.0372438,   51.6076,/T,  128.2761,   Psc,
//  2026-Jan-23 05:00, , , 23 39 08.61, -00 41 55.5,  0.00254663229390, -0.0372071,   52.1219,/T,  127.7611,   Psc,
//  2026-Jan-23 06:00, , , 23 41 06.54, -00 26 01.5,  0.00254573729786, -0.0371699,   52.6365,/T,  127.2457,   Psc,
//  2026-Jan-23 07:00, , , 23 43 04.53, -00 10 06.9,  0.00254484320067, -0.0371323,   53.1515,/T,  126.7299,   Psc,
//  2026-Jan-23 08:00, , , 23 45 02.59, +00 05 48.4,  0.00254395001362, -0.0370943,   53.6669,/T,  126.2138,   Psc,
//  2026-Jan-23 09:00, , , 23 47 00.73, +00 21 44.3,  0.00254305774837, -0.0370557,   54.1826,/T,  125.6973,   Psc,
//  2026-Jan-23 10:00, , , 23 48 58.95, +00 37 40.7,  0.00254216641711, -0.0370166,   54.6988,/T,  125.1804,   Psc,
//  2026-Jan-23 11:00, , , 23 50 57.25, +00 53 37.6,  0.00254127603246, -0.0369770,   55.2152,/T,  124.6632,   Psc,
//  2026-Jan-23 12:00, , , 23 52 55.65, +01 09 34.8,  0.00254038660763, -0.0369368,   55.7321,/T,  124.1456,   Psc,
//  2026-Jan-23 13:00, , , 23 54 54.14, +01 25 32.3,  0.00253949815627, -0.0368960,   56.2493,/T,  123.6277,   Psc,
//  2026-Jan-23 14:00, , , 23 56 52.73, +01 41 30.0,  0.00253861069263, -0.0368547,   56.7669,/T,  123.1094,   Psc,
//  2026-Jan-23 15:00, , , 23 58 51.43, +01 57 27.9,  0.00253772423155, -0.0368127,   57.2848,/T,  122.5908,   Psc,
//  2026-Jan-23 16:00, , , 00 00 50.24, +02 13 25.8,  0.00253683878838, -0.0367700,   57.8032,/T,  122.0718,   Psc,
//  2026-Jan-23 17:00, , , 00 02 49.16, +02 29 23.8,  0.00253595437920, -0.0367267,   58.3218,/T,  121.5525,   Psc,
//  2026-Jan-23 18:00, , , 00 04 48.21, +02 45 21.7,  0.00253507102070, -0.0366827,   58.8409,/T,  121.0328,   Psc,
//  2026-Jan-23 19:00, , , 00 06 47.39, +03 01 19.5,  0.00253418873016, -0.0366379,   59.3603,/T,  120.5127,   Psc,
//  2026-Jan-23 20:00, , , 00 08 46.69, +03 17 17.0,  0.00253330752567, -0.0365924,   59.8801,/T,  119.9923,   Psc,
//  2026-Jan-23 21:00, , , 00 10 46.14, +03 33 14.3,  0.00253242742593, -0.0365461,   60.4002,/T,  119.4715,   Psc,
//  2026-Jan-23 22:00, , , 00 12 45.73, +03 49 11.2,  0.00253154845039, -0.0364989,   60.9208,/T,  118.9504,   Psc,
//  2026-Jan-23 23:00, , , 00 14 45.46, +04 05 07.7,  0.00253067061931, -0.0364509,   61.4416,/T,  118.4289,   Psc,
//  2026-Jan-24 00:00, , , 00 16 45.36, +04 21 03.7,  0.00252979395362, -0.0364020,   61.9629,/T,  117.9071,   Psc,
//  2026-Jan-24 01:00, , , 00 18 45.41, +04 36 59.0,  0.00252891847512, -0.0363522,   62.4845,/T,  117.3849,   Psc,
//  2026-Jan-24 02:00, , , 00 20 45.62, +04 52 53.8,  0.00252804420642, -0.0363015,   63.0065,/T,  116.8623,   Psc,
//  2026-Jan-24 03:00, , , 00 22 46.01, +05 08 47.8,  0.00252717117088, -0.0362497,   63.5288,/T,  116.3394,   Psc,
//  2026-Jan-24 04:00, , , 00 24 46.57, +05 24 40.9,  0.00252629939282, -0.0361969,   64.0516,/T,  115.8162,   Psc,
//  2026-Jan-24 05:00, , , 00 26 47.31, +05 40 33.2,  0.00252542889735, -0.0361431,   64.5746,/T,  115.2926,   Psc,
//  2026-Jan-24 06:00, , , 00 28 48.24, +05 56 24.6,  0.00252455971051, -0.0360882,   65.0981,/T,  114.7686,   Psc,
//  2026-Jan-24 07:00, , , 00 30 49.36, +06 12 14.9,  0.00252369185922, -0.0360321,   65.6219,/T,  114.2443,   Psc,
//  2026-Jan-24 08:00, , , 00 32 50.68, +06 28 04.0,  0.00252282537135, -0.0359748,   66.1461,/T,  113.7196,   Psc,
//  2026-Jan-24 09:00, , , 00 34 52.19, +06 43 52.0,  0.00252196027571, -0.0359163,   66.6706,/T,  113.1945,   Psc,
//  2026-Jan-24 10:00, , , 00 36 53.92, +06 59 38.7,  0.00252109660204, -0.0358566,   67.1955,/T,  112.6691,   Psc,
//  2026-Jan-24 11:00, , , 00 38 55.86, +07 15 24.0,  0.00252023438112, -0.0357956,   67.7208,/T,  112.1434,   Psc,
//  2026-Jan-24 12:00, , , 00 40 58.02, +07 31 07.9,  0.00251937364467, -0.0357332,   68.2464,/T,  111.6173,   Psc,
//  2026-Jan-24 13:00, , , 00 43 00.40, +07 46 50.3,  0.00251851442544, -0.0356695,   68.7725,/T,  111.0908,   Psc,
//  2026-Jan-24 14:00, , , 00 45 03.02, +08 02 31.0,  0.00251765675723, -0.0356043,   69.2988,/T,  110.5640,   Psc,
//  2026-Jan-24 15:00, , , 00 47 05.87, +08 18 10.1,  0.00251680067486, -0.0355377,   69.8256,/T,  110.0369,   Psc,
//  2026-Jan-24 16:00, , , 00 49 08.95, +08 33 47.5,  0.00251594621418, -0.0354695,   70.3527,/T,  109.5093,   Psc,
//  2026-Jan-24 17:00, , , 00 51 12.29, +08 49 22.9,  0.00251509341216, -0.0353998,   70.8802,/T,  108.9815,   Psc,
//  2026-Jan-24 18:00, , , 00 53 15.87, +09 04 56.5,  0.00251424230685, -0.0353285,   71.4080,/T,  108.4532,   Psc,
//  2026-Jan-24 19:00, , , 00 55 19.71, +09 20 28.0,  0.00251339293740, -0.0352555,   71.9362,/T,  107.9246,   Psc,
//  2026-Jan-24 20:00, , , 00 57 23.81, +09 35 57.5,  0.00251254534406, -0.0351809,   72.4648,/T,  107.3957,   Psc,
//  2026-Jan-24 21:00, , , 00 59 28.18, +09 51 24.8,  0.00251169956821, -0.0351044,   72.9938,/T,  106.8664,   Psc,
//  2026-Jan-24 22:00, , , 01 01 32.82, +10 06 49.8,  0.00251085565237, -0.0350263,   73.5231,/T,  106.3367,   Psc,
//  2026-Jan-24 23:00, , , 01 03 37.74, +10 22 12.4,  0.00251001364021, -0.0349462,   74.0528,/T,  105.8067,   Psc,
//  2026-Jan-25 00:00, , , 01 05 42.95, +10 37 32.6,  0.00250917357660, -0.0348643,   74.5828,/T,  105.2764,   Psc,
//  2026-Jan-25 01:00, , , 01 07 48.44, +10 52 50.3,  0.00250833550753, -0.0347804,   75.1132,/T,  104.7456,   Psc,
//  2026-Jan-25 02:00, , , 01 09 54.22, +11 08 05.4,  0.00250749948018, -0.0346946,   75.6440,/T,  104.2146,   Psc,
//  2026-Jan-25 03:00, , , 01 12 00.30, +11 23 17.7,  0.00250666554295, -0.0346067,   76.1752,/T,  103.6831,   Psc,
//  2026-Jan-25 04:00, , , 01 14 06.69, +11 38 27.3,  0.00250583374547, -0.0345168,   76.7067,/T,  103.1513,   Psc,
//  2026-Jan-25 05:00, , , 01 16 13.38, +11 53 34.0,  0.00250500413847, -0.0344247,   77.2386,/T,  102.6192,   Psc,
//  2026-Jan-25 06:00, , , 01 18 20.39, +12 08 37.8,  0.00250417677402, -0.0343304,   77.7708,/T,  102.0867,   Psc,
//  2026-Jan-25 07:00, , , 01 20 27.72, +12 23 38.4,  0.00250335170534, -0.0342339,   78.3034,/T,  101.5539,   Psc,
//  2026-Jan-25 08:00, , , 01 22 35.37, +12 38 35.9,  0.00250252898692, -0.0341351,   78.8364,/T,  101.0206,   Psc,
//  2026-Jan-25 09:00, , , 01 24 43.35, +12 53 30.2,  0.00250170867448, -0.0340339,   79.3698,/T,  100.4871,   Psc,
//  2026-Jan-25 10:00, , , 01 26 51.67, +13 08 21.2,  0.00250089082501, -0.0339304,   79.9035,/T,   99.9532,   Psc,
//  2026-Jan-25 11:00, , , 01 29 00.32, +13 23 08.7,  0.00250007549668, -0.0338244,   80.4376,/T,   99.4189,   Psc,
//  2026-Jan-25 12:00, , , 01 31 09.32, +13 37 52.7,  0.00249926274898, -0.0337159,   80.9721,/T,   98.8843,   Psc,
//  2026-Jan-25 13:00, , , 01 33 18.66, +13 52 33.1,  0.00249845264264, -0.0336049,   81.5069,/T,   98.3493,   Psc,
//  2026-Jan-25 14:00, , , 01 35 28.36, +14 07 09.8,  0.00249764523964, -0.0334912,   82.0421,/T,   97.8140,   Psc,
//  2026-Jan-25 15:00, , , 01 37 38.42, +14 21 42.6,  0.00249684060326, -0.0333749,   82.5776,/T,   97.2783,   Psc,
//  2026-Jan-25 16:00, , , 01 39 48.84, +14 36 11.6,  0.00249603879797, -0.0332559,   83.1135,/T,   96.7422,   Psc,
//  2026-Jan-25 17:00, , , 01 41 59.63, +14 50 36.6,  0.00249523988961, -0.0331342,   83.6498,/T,   96.2058,   Psc,
//  2026-Jan-25 18:00, , , 01 44 10.79, +15 04 57.4,  0.00249444394521, -0.0330096,   84.1865,/T,   95.6691,   Psc,
//  2026-Jan-25 19:00, , , 01 46 22.32, +15 19 14.1,  0.00249365103309, -0.0328822,   84.7235,/T,   95.1320,   Psc,
//  2026-Jan-25 20:00, , , 01 48 34.24, +15 33 26.5,  0.00249286122286, -0.0327518,   85.2609,/T,   94.5945,   Ari,
//  2026-Jan-25 21:00, , , 01 50 46.54, +15 47 34.5,  0.00249207458534, -0.0326185,   85.7986,/T,   94.0567,   Ari,
//  2026-Jan-25 22:00, , , 01 52 59.23, +16 01 38.0,  0.00249129119269, -0.0324822,   86.3367,/T,   93.5186,   Ari,
//  2026-Jan-25 23:00, , , 01 55 12.31, +16 15 36.9,  0.00249051111827, -0.0323427,   86.8752,/T,   92.9801,   Ari,
//  2026-Jan-26 00:00, , , 01 57 25.79, +16 29 31.1,  0.00248973443671, -0.0322002,   87.4140,/T,   92.4412,   Ari,
//  2026-Jan-26 01:00, , , 01 59 39.68, +16 43 20.6,  0.00248896122389, -0.0320545,   87.9532,/T,   91.9020,   Ari,
//  2026-Jan-26 02:00, , , 02 01 53.97, +16 57 05.1,  0.00248819155696, -0.0319055,   88.4928,/T,   91.3625,   Ari,
//  2026-Jan-26 03:00, , , 02 04 08.66, +17 10 44.6,  0.00248742551432, -0.0317533,   89.0327,/T,   90.8226,   Ari,
//  2026-Jan-26 04:00, , , 02 06 23.77, +17 24 19.1,  0.00248666317553, -0.0315977,   89.5730,/T,   90.2823,   Ari,
//  2026-Jan-26 05:00, , , 02 08 39.30, +17 37 48.3,  0.00248590462150, -0.0314388,   90.1136,/T,   89.7417,   Ari,
//  2026-Jan-26 06:00, , , 02 10 55.25, +17 51 12.2,  0.00248514993426, -0.0312764,   90.6546,/T,   89.2008,   Ari,
//  2026-Jan-26 07:00, , , 02 13 11.62, +18 04 30.7,  0.00248439919709, -0.0311105,   91.1960,/T,   88.6595,   Ari,
//  2026-Jan-26 08:00, , , 02 15 28.41, +18 17 43.7,  0.00248365249446, -0.0309411,   91.7377,/T,   88.1178,   Ari,
//  2026-Jan-26 09:00, , , 02 17 45.64, +18 30 51.1,  0.00248290991204, -0.0307681,   92.2798,/T,   87.5759,   Ari,
//  2026-Jan-26 10:00, , , 02 20 03.30, +18 43 52.7,  0.00248217153674, -0.0305915,   92.8222,/T,   87.0335,   Ari,
//  2026-Jan-26 11:00, , , 02 22 21.40, +18 56 48.5,  0.00248143745653, -0.0304111,   93.3650,/T,   86.4909,   Ari,
//  2026-Jan-26 12:00, , , 02 24 39.93, +19 09 38.3,  0.00248070776060, -0.0302271,   93.9081,/T,   85.9479,   Ari,
//  2026-Jan-26 13:00, , , 02 26 58.91, +19 22 22.1,  0.00247998253932, -0.0300393,   94.4516,/T,   85.4045,   Ari,
//  2026-Jan-26 14:00, , , 02 29 18.33, +19 34 59.7,  0.00247926188411, -0.0298477,   94.9954,/T,   84.8608,   Ari,
//  2026-Jan-26 15:00, , , 02 31 38.20, +19 47 31.0,  0.00247854588758, -0.0296522,   95.5396,/T,   84.3168,   Ari,
//  2026-Jan-26 16:00, , , 02 33 58.51, +19 59 56.0,  0.00247783464341, -0.0294527,   96.0842,/T,   83.7725,   Ari,
//  2026-Jan-26 17:00, , , 02 36 19.28, +20 12 14.5,  0.00247712824636, -0.0292493,   96.6291,/T,   83.2278,   Ari,
//  2026-Jan-26 18:00, , , 02 38 40.49, +20 24 26.4,  0.00247642679226, -0.0290420,   97.1743,/T,   82.6828,   Ari,
//  2026-Jan-26 19:00, , , 02 41 02.17, +20 36 31.5,  0.00247573037804, -0.0288305,   97.7199,/T,   82.1374,   Ari,
//  2026-Jan-26 20:00, , , 02 43 24.29, +20 48 29.9,  0.00247503910162, -0.0286150,   98.2658,/T,   81.5917,   Ari,
//  2026-Jan-26 21:00, , , 02 45 46.88, +21 00 21.3,  0.00247435306192, -0.0283953,   98.8121,/T,   81.0457,   Ari,
//  2026-Jan-26 22:00, , , 02 48 09.93, +21 12 05.7,  0.00247367235888, -0.0281715,   99.3587,/T,   80.4993,   Ari,
//  2026-Jan-26 23:00, , , 02 50 33.43, +21 23 42.9,  0.00247299709345, -0.0279435,   99.9056,/T,   79.9527,   Ari,
//  2026-Jan-27 00:00, , , 02 52 57.40, +21 35 12.9,  0.00247232736746, -0.0277112,  100.4529,/T,   79.4057,   Ari,
//  2026-Jan-27 01:00, , , 02 55 21.82, +21 46 35.5,  0.00247166328375, -0.0274746,  101.0005,/T,   78.8583,   Ari,
//  2026-Jan-27 02:00, , , 02 57 46.71, +21 57 50.6,  0.00247100494600, -0.0272337,  101.5485,/T,   78.3107,   Ari,
//  2026-Jan-27 03:00, , , 03 00 12.06, +22 08 58.1,  0.00247035245882, -0.0269884,  102.0967,/T,   77.7627,   Ari,
//  2026-Jan-27 04:00, , , 03 02 37.88, +22 19 57.9,  0.00246970592765, -0.0267387,  102.6454,/T,   77.2144,   Ari,
//  2026-Jan-27 05:00, , , 03 05 04.15, +22 30 49.9,  0.00246906545883, -0.0264846,  103.1943,/T,   76.6658,   Ari,
//  2026-Jan-27 06:00, , , 03 07 30.89, +22 41 34.0,  0.00246843115946, -0.0262261,  103.7436,/T,   76.1169,   Ari,
//  2026-Jan-27 07:00, , , 03 09 58.09, +22 52 10.0,  0.00246780313743, -0.0259630,  104.2932,/T,   75.5677,   Ari,
//  2026-Jan-27 08:00, , , 03 12 25.76, +23 02 37.9,  0.00246718150140, -0.0256954,  104.8431,/T,   75.0182,   Ari,
//  2026-Jan-27 09:00, , , 03 14 53.88, +23 12 57.5,  0.00246656636075, -0.0254232,  105.3933,/T,   74.4683,   Ari,
//  2026-Jan-27 10:00, , , 03 17 22.46, +23 23 08.7,  0.00246595782560, -0.0251465,  105.9439,/T,   73.9182,   Ari,
//  2026-Jan-27 11:00, , , 03 19 51.50, +23 33 11.5,  0.00246535600669, -0.0248651,  106.4948,/T,   73.3677,   Ari,
//  2026-Jan-27 12:00, , , 03 22 21.00, +23 43 05.7,  0.00246476101544, -0.0245791,  107.0459,/T,   72.8170,   Ari,
//  2026-Jan-27 13:00, , , 03 24 50.95, +23 52 51.1,  0.00246417296389, -0.0242884,  107.5974,/T,   72.2659,   Ari,
//  2026-Jan-27 14:00, , , 03 27 21.35, +24 02 27.8,  0.00246359196465, -0.0239930,  108.1492,/T,   71.7146,   Ari,
//  2026-Jan-27 15:00, , , 03 29 52.21, +24 11 55.5,  0.00246301813088, -0.0236929,  108.7014,/T,   71.1629,   Tau,
//  2026-Jan-27 16:00, , , 03 32 23.51, +24 21 14.2,  0.00246245157628, -0.0233881,  109.2538,/T,   70.6110,   Tau,
//  2026-Jan-27 17:00, , , 03 34 55.25, +24 30 23.8,  0.00246189241498, -0.0230785,  109.8065,/T,   70.0588,   Tau,
//  2026-Jan-27 18:00, , , 03 37 27.44, +24 39 24.1,  0.00246134076166, -0.0227642,  110.3595,/T,   69.5063,   Tau,
//  2026-Jan-27 19:00, , , 03 40 00.07, +24 48 15.1,  0.00246079673133, -0.0224451,  110.9128,/T,   68.9535,   Tau,
//  2026-Jan-27 20:00, , , 03 42 33.13, +24 56 56.7,  0.00246026043943, -0.0221211,  111.4664,/T,   68.4004,   Tau,
//  2026-Jan-27 21:00, , , 03 45 06.63, +25 05 28.6,  0.00245973200174, -0.0217924,  112.0203,/T,   67.8471,   Tau,
//  2026-Jan-27 22:00, , , 03 47 40.55, +25 13 51.0,  0.00245921153434, -0.0214588,  112.5744,/T,   67.2935,   Tau,
//  2026-Jan-27 23:00, , , 03 50 14.90, +25 22 03.5,  0.00245869915363, -0.0211204,  113.1289,/T,   66.7396,   Tau,
//  2026-Jan-28 00:00, , , 03 52 49.67, +25 30 06.2,  0.00245819497621, -0.0207771,  113.6836,/T,   66.1855,   Tau,
//  2026-Jan-28 01:00, , , 03 55 24.85, +25 37 58.9,  0.00245769911887, -0.0204290,  114.2386,/T,   65.6311,   Tau,
//  2026-Jan-28 02:00, , , 03 58 00.44, +25 45 41.6,  0.00245721169863, -0.0200760,  114.7939,/T,   65.0764,   Tau,
//  2026-Jan-28 03:00, , , 04 00 36.43, +25 53 14.1,  0.00245673283255, -0.0197181,  115.3494,/T,   64.5215,   Tau,
//  2026-Jan-28 04:00, , , 04 03 12.82, +26 00 36.3,  0.00245626263786, -0.0193554,  115.9052,/T,   63.9663,   Tau,
//  2026-Jan-28 05:00, , , 04 05 49.61, +26 07 48.2,  0.00245580123179, -0.0189878,  116.4612,/T,   63.4109,   Tau,
//  2026-Jan-28 06:00, , , 04 08 26.78, +26 14 49.6,  0.00245534873157, -0.0186153,  117.0176,/T,   62.8552,   Tau,
//  2026-Jan-28 07:00, , , 04 11 04.33, +26 21 40.5,  0.00245490525440, -0.0182380,  117.5741,/T,   62.2993,   Tau,
//  2026-Jan-28 08:00, , , 04 13 42.25, +26 28 20.8,  0.00245447091746, -0.0178557,  118.1309,/T,   61.7432,   Tau,
//  2026-Jan-28 09:00, , , 04 16 20.55, +26 34 50.3,  0.00245404583776, -0.0174687,  118.6880,/T,   61.1868,   Tau,
//  2026-Jan-28 10:00, , , 04 18 59.20, +26 41 09.0,  0.00245363013215, -0.0170768,  119.2453,/T,   60.6302,   Tau,
//  2026-Jan-28 11:00, , , 04 21 38.20, +26 47 16.8,  0.00245322391729, -0.0166800,  119.8028,/T,   60.0733,   Tau,
//  2026-Jan-28 12:00, , , 04 24 17.55, +26 53 13.6,  0.00245282730964, -0.0162784,  120.3606,/T,   59.5163,   Tau,
//  2026-Jan-28 13:00, , , 04 26 57.24, +26 58 59.4,  0.00245244042532, -0.0158720,  120.9186,/T,   58.9590,   Tau,
//  2026-Jan-28 14:00, , , 04 29 37.26, +27 04 34.0,  0.00245206338011, -0.0154607,  121.4768,/T,   58.4016,   Tau,
//  2026-Jan-28 15:00, , , 04 32 17.59, +27 09 57.4,  0.00245169628951, -0.0150447,  122.0353,/T,   57.8439,   Tau,
//  2026-Jan-28 16:00, , , 04 34 58.24, +27 15 09.5,  0.00245133926849, -0.0146240,  122.5939,/T,   57.2860,   Tau,
//  2026-Jan-28 17:00, , , 04 37 39.19, +27 20 10.2,  0.00245099243164, -0.0141984,  123.1528,/T,   56.7279,   Tau,
// $$EOE
// ****************************************************************************************************************
// Column meaning:
 
// TIME

//   Times PRIOR to 1962 are UT1, a mean-solar time closely related to the
// prior but now-deprecated GMT. Times AFTER 1962 are in UTC, the current
// civil or "wall-clock" time-scale. UTC is kept within 0.9 seconds of UT1
// using integer leap-seconds for 1972 and later years.

//   Conversion from the internal Barycentric Dynamical Time (TDB) of solar
// system dynamics to the non-uniform civil UT time-scale requested for output
// has not been determined for UTC times after the next July or January 1st.
// Therefore, the last known leap-second is used as a constant over future
// intervals.

//   Time tags refer to the UT time-scale conversion from TDB on Earth
// regardless of observer location within the solar system, although clock
// rates may differ due to the local gravity field and no analog to "UT"
// may be defined for that location.

//   Any 'b' symbol in the 1st-column denotes a B.C. date. First-column blank
// (" ") denotes an A.D. date.
 
// CALENDAR SYSTEM

//   Mixed calendar mode was active such that calendar dates after AD 1582-Oct-15
// (if any) are in the modern Gregorian system. Dates prior to 1582-Oct-5 (if any)
// are in the Julian calendar system, which is automatically extended for dates
// prior to its adoption on 45-Jan-1 BC.  The Julian calendar is useful for
// matching historical dates. The Gregorian calendar more accurately corresponds
// to the Earth's orbital motion and seasons. A "Gregorian-only" calendar mode is
// available if such physical events are the primary interest.

//   NOTE: "n.a." in output means quantity "not available" at the print-time.
 
//  'R.A._(ICRF), DEC__(ICRF),' =
//   Astrometric right ascension and declination of the target center with
// respect to the observing site (coordinate origin) in the reference frame of
// the planetary ephemeris (ICRF). Compensated for down-leg light-time delay
// aberration.

//   Units: RA  in hours-minutes-seconds of time,    HH MM SS.ff{ffff}
//          DEC in degrees-minutes-seconds of arc,  sDD MN SC.f{ffff}
 
//  'delta,     deldot,' =
//    Apparent range ("delta", light-time aberrated) and range-rate ("delta-dot")
// of the target center relative to the observer. A positive "deldot" means the
// target center is moving away from the observer, negative indicates movement
// toward the observer.  Units: AU and KM/S
 
//  'S-O-T,/r,' =
//    Sun-Observer-Target apparent SOLAR ELONGATION ANGLE seen from the observers'
// location at print-time.

//    The '/r' column provides a code indicating the targets' apparent position
// relative to the Sun in the observers' sky, as described below:

//    Case A: For an observing location on the surface of a rotating body, that
// body rotational sense is considered:

//     /T indicates target TRAILS Sun   (evening sky: rises and sets AFTER Sun)
//     /L indicates target LEADS Sun    (morning sky: rises and sets BEFORE Sun)

//    Case B: For an observing point that does not have a rotational model (such
// as a spacecraft), the "leading" and "trailing" condition is defined by the
// observers' heliocentric ORBITAL motion:

//     * If continuing in the observers' current direction of heliocentric
//        motion would encounter the targets' apparent longitude first, followed
//        by the Sun's, the target LEADS the Sun as seen by the observer.

//     * If the Sun's apparent longitude would be encountered first, followed
//        by the targets', the target TRAILS the Sun.

//    Two other codes can be output:
//     /* indicates observer is Sun-centered    (undefined)
//     /? Target is aligned with Sun center     (no lead or trail)

//    The S-O-T solar elongation angle is numerically the minimum separation
// angle of the Sun and target in the sky in any direction. It does NOT indicate
// the amount of separation in the leading or trailing directions, which would
// be defined along the equator of a spherical coordinate system.

//    Units: DEGREES
 
//  'S-T-O,' =
//    The Sun-Target-Observer angle; the interior vertex angle at target center
// formed by a vector from the target to the apparent center of the Sun (at
// reflection time on the target) and the apparent vector from target to the
// observer at print-time. Slightly different from true PHASE ANGLE (requestable
// separately) at the few arcsecond level in that it includes stellar aberration
// on the down-leg from target to observer.  Units: DEGREES
 
//  'Cnst,' =
//    Constellation ID; the 3-letter abbreviation for the name of the
// constellation containing the target centers' astrometric position,
// as defined by IAU (1930) boundary delineation.  See documentation
// for list of abbreviations.

// Computations by ...

//     Solar System Dynamics Group, Horizons On-Line Ephemeris System
//     4800 Oak Grove Drive, Jet Propulsion Laboratory
//     Pasadena, CA  91109   USA

//     General site: https://ssd.jpl.nasa.gov/
//     Mailing list: https://ssd.jpl.nasa.gov/email_list.html
//     System news : https://ssd.jpl.nasa.gov/horizons/news.html
//     User Guide  : https://ssd.jpl.nasa.gov/horizons/manual.html
//     Connect     : browser        https://ssd.jpl.nasa.gov/horizons/app.html#/x
//                   API            https://ssd-api.jpl.nasa.gov/doc/horizons.html
//                   command-line   telnet ssd.jpl.nasa.gov 6775
//                   e-mail/batch   https://ssd.jpl.nasa.gov/ftp/ssd/horizons_batch.txt
//                   scripts        https://ssd.jpl.nasa.gov/ftp/ssd/SCRIPTS
//     Author      : Jon.D.Giorgini@jpl.nasa.gov

// ****************************************************************************************************************
