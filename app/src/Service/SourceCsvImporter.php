<?php

namespace App\Service;

use App\Entity\Source;
use App\Enum\FetchMode;
use App\Enum\SourceType;
use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SourceCsvImporter
{
    private const SEPARATOR = ';';
    private const EXPECTED_HEADERS = ['name', 'type', 'url', 'isActive', 'fetchMode', 'feedUrl', 'notes'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SourceRepository $sourceRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function import(UploadedFile $file, bool $persist = true, string $duplicateRule = 'name'): SourceCsvImportResult
    {
        if (!in_array($duplicateRule, ['name', 'feed_url', 'name_feed_url'], true)) {
            $duplicateRule = 'name';
        }

        $fileErrors = $this->validateFile($file);
        if ($fileErrors !== []) {
            return new SourceCsvImportResult(0, $fileErrors);
        }

        [$rows, $errors] = $this->readRows($file);

        if ($errors !== []) {
            return new SourceCsvImportResult(0, $errors);
        }

        [$sources, $validationErrors] = $this->validateRows($rows, $duplicateRule);

        if ($validationErrors !== []) {
            return new SourceCsvImportResult(0, $validationErrors);
        }

        if (!$persist) {
            return new SourceCsvImportResult(count($sources));
        }

        try {
            $this->entityManager->wrapInTransaction(function () use ($sources): void {
                foreach ($sources as $source) {
                    $this->entityManager->persist($source);
                }

                $this->entityManager->flush();
            });
        } catch (\Throwable $exception) {
            return new SourceCsvImportResult(0, ['Import annule : '.$exception->getMessage()]);
        }

        return new SourceCsvImportResult(count($sources));
    }

    /**
     * @return array<int, string>
     */
    private function validateFile(UploadedFile $file): array
    {
        $errors = [];

        if (mb_strtolower($file->getClientOriginalExtension()) !== 'csv') {
            $errors[] = 'Le fichier doit avoir l extension .csv.';
        }

        if ($file->getSize() !== null && $file->getSize() > 2 * 1024 * 1024) {
            $errors[] = 'Le fichier CSV ne doit pas depasser 2 Mo.';
        }

        return $errors;
    }

    /**
     * @return array{0: array<int, array{line: int, values: array<string, string>}>, 1: array<int, string>}
     */
    private function readRows(UploadedFile $file): array
    {
        $handle = fopen($file->getPathname(), 'rb');
        if ($handle === false) {
            return [[], ['Impossible de lire le fichier CSV.']];
        }

        $errors = [];
        $rows = [];
        $headers = null;
        $lineNumber = 0;

        while (($data = fgetcsv($handle, null, self::SEPARATOR)) !== false) {
            ++$lineNumber;
            $data = array_map([$this, 'cleanCell'], $data);

            if ($this->isEmptyLine($data)) {
                continue;
            }

            if ($headers === null) {
                $headers = $data;
                if ($headers !== self::EXPECTED_HEADERS) {
                    $errors[] = sprintf(
                        'Ligne 1 : en-tetes invalides. Attendu : %s.',
                        implode(self::SEPARATOR, self::EXPECTED_HEADERS),
                    );
                    break;
                }

                continue;
            }

            if (count($data) !== count(self::EXPECTED_HEADERS)) {
                $errors[] = sprintf('Ligne %d : nombre de colonnes invalide.', $lineNumber);
                continue;
            }

            $rows[] = [
                'line' => $lineNumber,
                'values' => array_combine(self::EXPECTED_HEADERS, $data),
            ];
        }

        fclose($handle);

        if ($headers === null && $errors === []) {
            $errors[] = 'Le fichier CSV est vide.';
        }

        if ($rows === [] && $errors === []) {
            $errors[] = 'Le fichier CSV ne contient aucune source a importer.';
        }

        return [$rows, $errors];
    }

    /**
     * @param array<int, array{line: int, values: array<string, string>}> $rows
     *
     * @return array{0: array<int, Source>, 1: array<int, string>}
     */
    private function validateRows(array $rows, string $duplicateRule): array
    {
        $errors = [];
        $sources = [];
        $seenNames = [];
        $seenFeedUrls = [];
        $seenNameFeedUrls = [];
        $existingNames = $this->existingNames();
        $existingFeedUrls = $this->existingFeedUrls();
        $existingNameFeedUrls = $this->existingNameFeedUrls();

        foreach ($rows as $row) {
            $line = $row['line'];
            $values = $row['values'];
            $lineErrors = [];

            if ($values['name'] === '') {
                $lineErrors[] = 'le nom est obligatoire';
            }

            $normalizedName = $this->normalizeKey($values['name']);
            $normalizedFeedUrl = $this->normalizeKey($values['feedUrl']);
            $normalizedNameFeedUrl = $normalizedName.'|'.$normalizedFeedUrl;

            if ($normalizedName !== '') {
                if ($duplicateRule === 'name') {
                    if (isset($seenNames[$normalizedName])) {
                        $lineErrors[] = sprintf('doublon dans le CSV avec la ligne %d pour le nom "%s"', $seenNames[$normalizedName], $values['name']);
                    } else {
                        $seenNames[$normalizedName] = $line;
                    }

                    if (isset($existingNames[$normalizedName])) {
                        $lineErrors[] = sprintf('une source nommee "%s" existe deja', $values['name']);
                    }
                }
            }

            if ($normalizedFeedUrl !== '') {
                if ($duplicateRule === 'feed_url') {
                    if (isset($seenFeedUrls[$normalizedFeedUrl])) {
                        $lineErrors[] = sprintf('doublon dans le CSV avec la ligne %d pour le flux "%s"', $seenFeedUrls[$normalizedFeedUrl], $values['feedUrl']);
                    } else {
                        $seenFeedUrls[$normalizedFeedUrl] = $line;
                    }

                    if (isset($existingFeedUrls[$normalizedFeedUrl])) {
                        $lineErrors[] = sprintf('une source avec le flux "%s" existe deja', $values['feedUrl']);
                    }
                }
            }

            if ($duplicateRule === 'name_feed_url' && $normalizedName !== '' && $normalizedFeedUrl !== '') {
                if (isset($seenNameFeedUrls[$normalizedNameFeedUrl])) {
                    $lineErrors[] = sprintf('doublon dans le CSV avec la ligne %d pour le couple nom + flux', $seenNameFeedUrls[$normalizedNameFeedUrl]);
                } else {
                    $seenNameFeedUrls[$normalizedNameFeedUrl] = $line;
                }

                if (isset($existingNameFeedUrls[$normalizedNameFeedUrl])) {
                    $lineErrors[] = sprintf('une source avec ce couple nom + flux existe deja');
                }
            }

            $type = SourceType::tryFrom($values['type']);
            if (!$type instanceof SourceType) {
                $lineErrors[] = sprintf('type invalide "%s"', $values['type']);
            }

            $fetchMode = FetchMode::tryFrom($values['fetchMode']);
            if (!$fetchMode instanceof FetchMode) {
                $lineErrors[] = sprintf('mode d import invalide "%s"', $values['fetchMode']);
            }

            $isActive = $this->parseBoolean($values['isActive']);
            if ($isActive === null) {
                $lineErrors[] = sprintf('booleen isActive invalide "%s"', $values['isActive']);
            }

            if ($values['url'] !== '' && !$this->isUrl($values['url'])) {
                $lineErrors[] = 'url invalide';
            }

            if ($values['feedUrl'] !== '' && !$this->isUrl($values['feedUrl'])) {
                $lineErrors[] = 'feedUrl invalide';
            }

            if ($fetchMode === FetchMode::Rss && $values['feedUrl'] === '') {
                $lineErrors[] = 'feedUrl est obligatoire quand fetchMode vaut rss';
            }

            if ($lineErrors !== []) {
                $errors[] = sprintf('Ligne %d : %s.', $line, implode('; ', $lineErrors));
                continue;
            }

            $source = (new Source())
                ->setName($values['name'])
                ->setType($type)
                ->setUrl($values['url'] !== '' ? $values['url'] : null)
                ->setIsActive((bool) $isActive)
                ->setFetchMode($fetchMode)
                ->setFeedUrl($values['feedUrl'] !== '' ? $values['feedUrl'] : null)
                ->setNotes($values['notes'] !== '' ? $values['notes'] : null);

            $violations = $this->validator->validate($source);
            if (count($violations) > 0) {
                foreach ($violations as $violation) {
                    $errors[] = sprintf('Ligne %d : %s %s.', $line, $violation->getPropertyPath(), $violation->getMessage());
                }

                continue;
            }

            $sources[] = $source;
        }

        return [$errors === [] ? $sources : [], $errors];
    }

    /**
     * @return array<string, true>
     */
    private function existingNames(): array
    {
        $names = [];

        foreach ($this->sourceRepository->findAll() as $source) {
            $names[$this->normalizeKey($source->getName())] = true;
        }

        return $names;
    }

    /**
     * @return array<string, true>
     */
    private function existingFeedUrls(): array
    {
        $feedUrls = [];

        foreach ($this->sourceRepository->findAll() as $source) {
            if ($source->getFeedUrl() !== null && trim($source->getFeedUrl()) !== '') {
                $feedUrls[$this->normalizeKey($source->getFeedUrl())] = true;
            }
        }

        return $feedUrls;
    }

    /**
     * @return array<string, true>
     */
    private function existingNameFeedUrls(): array
    {
        $pairs = [];

        foreach ($this->sourceRepository->findAll() as $source) {
            if ($source->getFeedUrl() !== null && trim($source->getFeedUrl()) !== '') {
                $pairs[$this->normalizeKey($source->getName()).'|'.$this->normalizeKey($source->getFeedUrl())] = true;
            }
        }

        return $pairs;
    }

    private function cleanCell(string $value): string
    {
        return trim(preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value);
    }

    /**
     * @param array<int, string> $data
     */
    private function isEmptyLine(array $data): bool
    {
        foreach ($data as $value) {
            if (trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parseBoolean(string $value): ?bool
    {
        $value = mb_strtolower($value);

        return match ($value) {
            '1', 'true', 'yes', 'oui', 'o' => true,
            '0', 'false', 'no', 'non', 'n' => false,
            default => null,
        };
    }

    private function isUrl(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function normalizeKey(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
