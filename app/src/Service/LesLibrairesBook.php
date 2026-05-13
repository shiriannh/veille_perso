<?php

namespace App\Service;

class LesLibrairesBook
{
    /**
     * @param array<int, string> $authors
     * @param array<int, string> $categories
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public string $title,
        public string $url,
        public array $authors = [],
        public ?string $format = null,
        public ?string $ean13 = null,
        public ?string $isbn = null,
        public ?string $publisher = null,
        public ?\DateTimeImmutable $publishedAt = null,
        public ?string $collection = null,
        public ?int $pageCount = null,
        public ?string $language = null,
        public ?string $description = null,
        public array $categories = [],
        public array $raw = [],
    ) {
    }

    public function mainIdentifier(): ?string
    {
        return $this->isbn !== null && $this->isbn !== ''
            ? 'isbn:'.$this->isbn
            : ($this->ean13 !== null && $this->ean13 !== '' ? 'ean13:'.$this->ean13 : null);
    }
}
