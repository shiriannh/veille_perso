<?php

namespace App\Service;

use App\Entity\Entry;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OptionalAiEntryAnalyzer
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @param array<string, mixed> $signals
     *
     * @return array<string, mixed>|null
     */
    public function analyze(Entry $entry, array $signals): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            return null;
        }

        $model = $this->model();
        $prompt = [
            'title' => $entry->getTitle(),
            'source' => $entry->getSource()?->getName(),
            'rawContent' => mb_substr((string) $entry->getRawContent(), 0, 4000),
            'ruleSignals' => $signals,
            'expectedJsonShape' => [
                'relevant' => 'boolean',
                'clickbait' => 'boolean',
                'relevanceScore' => 'number 0-100',
                'clickbaitScore' => 'number 0-100',
                'confidence' => 'number 0-1',
                'reasons' => 'array of short strings',
                'suggestedDecision' => 'relevant|maybe_relevant|ignored|clickbait',
            ],
        ];

        try {
            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/responses', [
                'auth_bearer' => $apiKey,
                'json' => [
                    'model' => $model,
                    'input' => [
                        [
                            'role' => 'system',
                            'content' => 'Classify one cultural-watch RSS entry. Return only compact JSON matching the requested shape. Keep decisions explainable and conservative.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode($prompt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ],
                    ],
                ],
                'timeout' => 30,
            ]);

            $raw = $response->toArray(false);
            $parsed = $this->extractJson($raw);

            return [
                'model' => $model,
                'raw' => $raw,
                'parsed' => $parsed,
            ];
        } catch (\Throwable $exception) {
            return [
                'model' => $model,
                'raw' => ['error' => $exception->getMessage()],
                'parsed' => null,
            ];
        }
    }

    public function isEnabled(): bool
    {
        return filter_var($this->env('OPENAI_ANALYSIS_ENABLED', '0'), FILTER_VALIDATE_BOOL);
    }

    public function model(): string
    {
        return $this->env('OPENAI_ANALYSIS_MODEL', 'gpt-5.4-mini');
    }

    private function apiKey(): string
    {
        return $this->env('OPENAI_API_KEY', '');
    }

    private function env(string $name, string $default): string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? $default;

        return is_string($value) ? $value : $default;
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return array<string, mixed>|null
     */
    private function extractJson(array $raw): ?array
    {
        $text = $raw['output_text'] ?? null;

        if (!is_string($text) && isset($raw['output']) && is_array($raw['output'])) {
            $chunks = [];
            foreach ($raw['output'] as $output) {
                if (!is_array($output) || !isset($output['content']) || !is_array($output['content'])) {
                    continue;
                }

                foreach ($output['content'] as $content) {
                    if (is_array($content) && isset($content['text']) && is_string($content['text'])) {
                        $chunks[] = $content['text'];
                    }
                }
            }

            $text = implode("\n", $chunks);
        }

        if (!is_string($text) || trim($text) === '') {
            return null;
        }

        $text = trim($text);
        $text = preg_replace('/^```json\s*|\s*```$/', '', $text) ?? $text;
        $decoded = json_decode(trim($text), true);

        return is_array($decoded) ? $decoded : null;
    }
}
