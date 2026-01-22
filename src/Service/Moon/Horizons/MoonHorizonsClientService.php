<?php

namespace App\Service\Moon\Horizons;

use Symfony\Contracts\HttpClient\HttpClientInterface;

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
        string $quantities
    ): array {
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
