<?php

namespace App\Service\Moon\Horizons;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client Horizons (HTTP).
 * Pourquoi: centraliser les requetes et normaliser les quantites demandees.
 * Infos: Horizons n'accepte pas les ranges ("1-49"), on les expand en CSV.
 */
final class MoonHorizonsClientService
{
    private const HORIZONS_ENDPOINT = 'https://ssd.jpl.nasa.gov/api/horizons.api';

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return array{status:int, body:string}
     */
    public function fetchEphemeris(
        \DateTimeInterface $start,
        \DateTimeInterface $stop,
        string $target,
        string $center,
        string $step,
        string $quantities,
        array $extraQuery = []
    ): array {
        $normalizedQuantities = $this->normalizeQuantities($quantities);
        $query = [
            'format' => 'text',
            'COMMAND' => $this->quoteParam($target),
            'CENTER' => $this->quoteParam($center),
            'MAKE_EPHEM' => $this->quoteParam('YES'),
            'EPHEM_TYPE' => $this->quoteParam('OBSERVER'),
            'START_TIME' => $this->quoteParam($start->format('Y-m-d H:i')),
            'STOP_TIME' => $this->quoteParam($stop->format('Y-m-d H:i')),
            'STEP_SIZE' => $this->quoteParam($step),
            'QUANTITIES' => $this->quoteParam($normalizedQuantities),
            'CSV_FORMAT' => $this->quoteParam('YES'),
            'OUT_UNITS' => $this->quoteParam('KM-S'),
        ];
        foreach ($extraQuery as $key => $value) {
            $query[$key] = $this->quoteParam((string) $value);
        }

        try {
            $response = $this->httpClient->request('GET', self::HORIZONS_ENDPOINT, [
                'query' => $query,
            ]);
            $status = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (\Throwable $e) {
            throw new \RuntimeException('HTTP request failed: ' . $e->getMessage(), 0, $e);
        }

        return [
            'status' => $status,
            'body' => $body,
        ];
    }

    public function normalizeQuantities(string $quantities): string
    {
        $clean = trim($quantities);
        if ($clean === '') {
            return $quantities;
        }

        $tokens = preg_split('/[,\s;]+/', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $values = [];
        $seen = [];

        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }

            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $token, $matches) === 1) {
                $start = (int) $matches[1];
                $stop = (int) $matches[2];
                if ($start > $stop) {
                    [$start, $stop] = [$stop, $start];
                }
                for ($i = $start; $i <= $stop; $i++) {
                    if ($i <= 0 || isset($seen[$i])) {
                        continue;
                    }
                    $seen[$i] = true;
                    $values[] = (string) $i;
                }
                continue;
            }

            if (ctype_digit($token)) {
                $value = (int) $token;
                if ($value > 0 && !isset($seen[$value])) {
                    $seen[$value] = true;
                    $values[] = (string) $value;
                }
            }
        }

        if (!$values) {
            return $quantities;
        }

        return implode(',', $values);
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
}
