<?php

namespace App\Service;

class SourceCsvImportResult
{
    /**
     * @param array<int, string> $errors
     */
    public function __construct(
        private readonly int $importedCount,
        private readonly array $errors = [],
    ) {
    }

    public function importedCount(): int
    {
        return $this->importedCount;
    }

    /**
     * @return array<int, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
