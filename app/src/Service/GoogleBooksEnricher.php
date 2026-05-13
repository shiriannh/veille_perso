<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleBooksEnricher
{
    /**
     * @var array<string, array<string, mixed>|null>
     */
    private array $cache = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey = '',
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function enrich(?string $isbn): ?array
    {
        $isbn = $this->normalizeIsbn((string) $isbn);
        if ($isbn === '' || trim($this->apiKey) === '') {
            return null;
        }

        if (array_key_exists($isbn, $this->cache)) {
            return $this->cache[$isbn];
        }

        try {
            $response = $this->httpClient->request('GET', 'https://www.googleapis.com/books/v1/volumes', [
                'query' => [
                    'q' => 'isbn:'.$isbn,
                    'key' => $this->apiKey,
                ],
                'timeout' => 8,
            ]);

            if ($response->getStatusCode() === 429 || $response->getStatusCode() >= 400) {
                return $this->cache[$isbn] = null;
            }

            $payload = $response->toArray(false);
            $item = is_array($payload['items'][0] ?? null) ? $payload['items'][0] : null;
            $volumeInfo = is_array($item['volumeInfo'] ?? null) ? $item['volumeInfo'] : null;
            if ($volumeInfo === null) {
                return $this->cache[$isbn] = null;
            }

            return $this->cache[$isbn] = [
                'description' => is_string($volumeInfo['description'] ?? null) ? $volumeInfo['description'] : null,
                'categories' => array_values(array_filter(array_map('strval', is_array($volumeInfo['categories'] ?? null) ? $volumeInfo['categories'] : []))),
                'pageCount' => is_numeric($volumeInfo['pageCount'] ?? null) ? (int) $volumeInfo['pageCount'] : null,
                'language' => is_string($volumeInfo['language'] ?? null) ? $volumeInfo['language'] : null,
                'imageLinks' => is_array($volumeInfo['imageLinks'] ?? null) ? $volumeInfo['imageLinks'] : [],
                'publisher' => is_string($volumeInfo['publisher'] ?? null) ? $volumeInfo['publisher'] : null,
                'publishedDate' => is_string($volumeInfo['publishedDate'] ?? null) ? $volumeInfo['publishedDate'] : null,
                'authors' => array_values(array_filter(array_map('strval', is_array($volumeInfo['authors'] ?? null) ? $volumeInfo['authors'] : []))),
                'raw' => $volumeInfo,
            ];
        } catch (\Throwable) {
            return $this->cache[$isbn] = null;
        }
    }

    private function normalizeIsbn(string $isbn): string
    {
        return preg_replace('/[^0-9Xx]/', '', $isbn) ?? '';
    }
}
