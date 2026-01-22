<?php

namespace App\Service\Moon\Horizons;

final class MoonHorizonsParseResult
{
    /**
     * @param array<int, string>|null $header
     * @param array<int, array{raw:string, cols:array<int, string>}> $rows
     * @param array<string, int|null> $columnMap
     */
    public function __construct(
        private ?array $header,
        private array $rows,
        private ?string $headerLine,
        private array $columnMap,
    ) {
    }

    /**
     * @return array<int, string>|null
     */
    public function getHeader(): ?array
    {
        return $this->header;
    }

    /**
     * @return array<int, array{raw:string, cols:array<int, string>}>
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    public function getHeaderLine(): ?string
    {
        return $this->headerLine;
    }

    /**
     * @return array<string, int|null>
     */
    public function getColumnMap(): array
    {
        return $this->columnMap;
    }
}
