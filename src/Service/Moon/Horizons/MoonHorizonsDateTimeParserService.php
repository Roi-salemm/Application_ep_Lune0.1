<?php

namespace App\Service\Moon\Horizons;

final class MoonHorizonsDateTimeParserService
{
    public function parseInput(string $input, \DateTimeZone $tz): ?\DateTime
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

    public function parseHorizonsTimestamp(?string $value, \DateTimeZone $tz): ?\DateTime
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
}
