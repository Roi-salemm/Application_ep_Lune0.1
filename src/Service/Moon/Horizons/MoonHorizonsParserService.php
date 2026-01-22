<?php

namespace App\Service\Moon\Horizons;

final class MoonHorizonsParserService
{
    public function parseResponse(string $body): MoonHorizonsParseResult
    {
        [$header, $rows, $headerLine] = $this->extractCsvRows($body);
        $columnMap = $this->buildColumnMap($header);

        return new MoonHorizonsParseResult($header, $rows, $headerLine, $columnMap);
    }

    /**
     * @return array{0: array<int, string>|null, 1: array<int, array{raw:string, cols:array<int, string>}>, 2: string|null}
     */
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

    /**
     * @param array<int, string>|null $header
     * @return array<string, int|null>
     */
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

    public function formatColumnMap(array $map): string
    {
        $pairs = [];
        foreach ($map as $key => $index) {
            if ($index !== null) {
                $pairs[] = $key . '=' . $index;
            }
        }

        return $pairs ? implode(', ', $pairs) : 'no columns matched';
    }

    /**
     * @param array<int, string> $header
     * @param array<int, string> $patterns
     */
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

    /**
     * @param array<int, string> $lines
     */
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
