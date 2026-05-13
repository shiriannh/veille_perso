<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class LesLibrairesConnector
{
    private const BASE_URL = 'https://www.leslibraires.fr';
    private const RAYON_PATH = '/rayon/science-fiction-fantastique-fantasy/';
    private const MAX_LIST_PAGES = 5;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return array<int, array{title: string, url: string, author: ?string, publishedAt: ?\DateTimeImmutable}>
     */
    public function candidates(string $window = '3m', ?string $sourceUrl = null): array
    {
        $url = $this->listUrl($window, $sourceUrl);
        $seen = [];
        $candidates = [];

        for ($page = 1; $page <= self::MAX_LIST_PAGES && $url !== null; ++$page) {
            $html = $this->fetch($url);
            $document = $this->document($html);
            $xpath = new \DOMXPath($document);

            foreach ($xpath->query('//a[contains(@href, "/livre/")]') as $link) {
                if (!$link instanceof \DOMElement) {
                    continue;
                }

                $href = $link->getAttribute('href');
                $bookUrl = $this->absoluteUrl($href);
                if ($bookUrl === null || isset($seen[$bookUrl])) {
                    continue;
                }

                $title = $this->clean($link->textContent);
                if ($title === '') {
                    $title = $this->clean($link->getAttribute('title'));
                }

                if ($title === '') {
                    continue;
                }

                $seen[$bookUrl] = true;
                $container = $this->nearestContainer($link);
                $candidates[] = [
                    'title' => $title,
                    'url' => $bookUrl,
                    'author' => $container !== null ? $this->firstItemProp($container, 'author') : null,
                    'publishedAt' => $container !== null ? $this->parseDate($this->firstItemProp($container, 'datePublished')) : null,
                ];
            }

            $url = $this->nextPageUrl($xpath);
        }

        return $candidates;
    }

    public function detail(string $url): LesLibrairesBook
    {
        $html = $this->fetch($url);
        $document = $this->document($html);
        $xpath = new \DOMXPath($document);

        $title = $this->first($xpath, '//*[@itemprop="name"]')
            ?? $this->first($xpath, '//h1')
            ?? '';
        $canonical = $this->firstAttribute($xpath, '//link[@rel="canonical"]', 'href') ?? $url;
        $authors = $this->all($xpath, '//*[@itemprop="author"]');
        $ean13 = $this->first($xpath, '//*[@itemprop="gtin13"]');
        $isbn = $this->first($xpath, '//*[@itemprop="isbn"]') ?? $ean13;
        $publisher = $this->first($xpath, '//*[@itemprop="publisher"]');
        $publishedAt = $this->parseDate($this->first($xpath, '//*[@itemprop="datePublished"]'));
        $pageCount = $this->parseInt($this->first($xpath, '//*[@itemprop="numberOfPages"]'));
        $language = $this->first($xpath, '//*[@itemprop="inLanguage"]');
        $description = $this->first($xpath, '//*[@itemprop="description"]')
            ?? $this->first($xpath, '//*[contains(@class, "resume") or contains(@class, "description")]');
        $format = $this->matchLabel($xpath, ['Format', 'Presentation']);
        $collection = $this->matchLabel($xpath, ['Collection']);

        return new LesLibrairesBook(
            title: $title,
            url: $canonical,
            authors: $authors,
            format: $format,
            ean13: $this->normalizeIdentifier($ean13),
            isbn: $this->normalizeIdentifier($isbn),
            publisher: $publisher,
            publishedAt: $publishedAt,
            collection: $collection,
            pageCount: $pageCount,
            language: $language,
            description: $description,
            categories: ['Romans VF', 'Science-fiction', 'Fantastique', 'Fantasy'],
            raw: [
                'connector' => 'leslibraires',
                'detailUrl' => $url,
                'canonicalUrl' => $canonical,
                'format' => $format,
                'collection' => $collection,
            ],
        );
    }

    public function listUrl(string $window, ?string $sourceUrl = null): string
    {
        $window = in_array($window, ['7d', '1m', '3m'], true) ? $window : '3m';
        $baseUrl = $this->normalizedRayonUrl($sourceUrl) ?? self::BASE_URL.self::RAYON_PATH;

        return $baseUrl.'?f_release_date=-'.$window;
    }

    private function normalizedRayonUrl(?string $sourceUrl): ?string
    {
        if ($sourceUrl === null || trim($sourceUrl) === '') {
            return null;
        }

        $parts = parse_url($sourceUrl);
        if (!is_array($parts) || !isset($parts['host']) || !str_ends_with((string) $parts['host'], 'leslibraires.fr')) {
            return null;
        }

        $path = $parts['path'] ?? self::RAYON_PATH;
        if (!str_contains($path, '/rayon/science-fiction-fantastique-fantasy/')) {
            $path = self::RAYON_PATH;
        }

        return 'https://www.leslibraires.fr'.rtrim($path, '/').'/';
    }

    private function fetch(string $url): string
    {
        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'veille-perso-local/1.0',
            ],
            'timeout' => 20,
        ]);

        return $response->getContent();
    }

    private function document(string $html): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }

    private function nearestContainer(\DOMElement $element): ?\DOMElement
    {
        $node = $element->parentNode;
        while ($node instanceof \DOMElement) {
            if (in_array(strtolower($node->tagName), ['article', 'li', 'div'], true)) {
                return $node;
            }

            $node = $node->parentNode;
        }

        return null;
    }

    private function firstItemProp(\DOMElement $container, string $itemProp): ?string
    {
        $xpath = new \DOMXPath($container->ownerDocument);
        foreach ($xpath->query('.//*[@itemprop="'.$itemProp.'"]', $container) as $node) {
            return $this->clean($node->textContent);
        }

        return null;
    }

    private function nextPageUrl(\DOMXPath $xpath): ?string
    {
        $href = $this->firstAttribute($xpath, '//a[@rel="next"]', 'href')
            ?? $this->firstAttribute($xpath, '//a[contains(translate(normalize-space(.), "SUIVANTNEXT", "suivantnext"), "suivant")]', 'href')
            ?? $this->firstAttribute($xpath, '//a[contains(translate(normalize-space(.), "SUIVANTNEXT", "suivantnext"), "next")]', 'href');

        return $href !== null ? $this->absoluteUrl($href) : null;
    }

    private function absoluteUrl(string $href): ?string
    {
        $href = trim($href);
        if ($href === '') {
            return null;
        }

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        if (!str_starts_with($href, '/')) {
            return null;
        }

        return self::BASE_URL.$href;
    }

    private function first(\DOMXPath $xpath, string $query): ?string
    {
        foreach ($xpath->query($query) as $node) {
            $value = $this->clean($node->textContent);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function firstAttribute(\DOMXPath $xpath, string $query, string $attribute): ?string
    {
        foreach ($xpath->query($query) as $node) {
            if ($node instanceof \DOMElement) {
                $value = trim($node->getAttribute($attribute));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function all(\DOMXPath $xpath, string $query): array
    {
        $values = [];
        foreach ($xpath->query($query) as $node) {
            $value = $this->clean($node->textContent);
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param array<int, string> $labels
     */
    private function matchLabel(\DOMXPath $xpath, array $labels): ?string
    {
        foreach ($labels as $label) {
            $query = sprintf('//*[contains(translate(normalize-space(.), "%s", "%s"), "%s")]', strtoupper($label), strtolower($label), strtolower($label));
            foreach ($xpath->query($query) as $node) {
                $text = $this->clean($node->textContent);
                if (preg_match('/'.preg_quote($label, '/').'\s*:?\s*(.+)$/iu', $text, $matches) === 1) {
                    return trim($matches[1]);
                }
            }
        }

        return null;
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseInt(?string $value): ?int
    {
        if ($value === null || preg_match('/\d+/', $value, $matches) !== 1) {
            return null;
        }

        return (int) $matches[0];
    }

    private function normalizeIdentifier(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/[^0-9Xx]/', '', $value) ?? '';

        return $normalized !== '' ? $normalized : null;
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($value))) ?? '');
    }
}
