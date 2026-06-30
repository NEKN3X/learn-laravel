<?php

declare(strict_types=1);

namespace TimelineCli\Infrastructure;

use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use TimelineCli\Domain\Exception\InvalidLogEntry;
use TimelineCli\Domain\LogEntry;
use TimelineCli\Domain\LogEntryRepository;

final class JsonLogEntryRepository implements LogEntryRepository
{
    public function __construct(private string $path)
    {
    }

    public function nextId(): int
    {
        $maxId = 0;

        foreach ($this->all() as $entry) {
            if ($entry->id() > $maxId) {
                $maxId = $entry->id();
            }
        }

        return $maxId + 1;
    }

    public function findById(int $id): ?LogEntry
    {
        foreach ($this->all() as $entry) {
            if ($entry->id() === $id) {
                return $entry;
            }
        }

        return null;
    }

    public function all(): array
    {
        $records = $this->readRecords();
        $entries = [];
        $seenIds = [];

        foreach ($records as $index => $record) {
            if (!is_array($record) || self::isListArray($record)) {
                throw new StorageFailure("Invalid Log Entry record at index {$index} in {$this->path}: expected object.");
            }

            $this->assertExpectedRecordKeys($record, "Log Entry record at index {$index} in {$this->path}");

            try {
                $entry = new LogEntry(
                    $record['id'],
                    $record['title'],
                    $record['content'],
                    $this->parseStoredDateTime($record['recorded_at'], 'recorded_at'),
                    $record['ended_at'] === null ? null : $this->parseStoredDateTime($record['ended_at'], 'ended_at'),
                    $record['context_id']
                );
            } catch (InvalidLogEntry $exception) {
                throw new StorageFailure(
                    "Invalid Log Entry record at index {$index} in {$this->path}: {$exception->getMessage()}",
                    0,
                    $exception
                );
            }

            if (isset($seenIds[$entry->id()])) {
                throw new StorageFailure("Duplicate Log Entry ID in {$this->path}: #{$entry->id()}");
            }

            $seenIds[$entry->id()] = true;
            $entries[] = $entry;
        }

        return $entries;
    }

    public function save(LogEntry $entry): void
    {
        $entries = $this->all();
        $saved = false;

        foreach ($entries as $index => $existingEntry) {
            if ($existingEntry->id() === $entry->id()) {
                $entries[$index] = $entry;
                $saved = true;
                break;
            }
        }

        if (!$saved) {
            $entries[] = $entry;
        }

        $this->writeEntries($entries);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readRecords(): array
    {
        $this->initializeStorageFile("[]\n");

        $json = file_get_contents($this->path);
        if ($json === false) {
            throw new StorageFailure("Could not read JSON storage file: {$this->path}");
        }

        try {
            $records = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new StorageFailure(
                'Storage file contains broken JSON: ' . $this->path . ' (' . $exception->getMessage() . '). Fix the file before running this command.',
                0,
                $exception
            );
        }

        if (!is_array($records) || !self::isListArray($records)) {
            throw new StorageFailure("Storage file must contain a JSON array of Log Entries: {$this->path}");
        }

        return $records;
    }

    /**
     * @param list<LogEntry> $entries
     */
    private function writeEntries(array $entries): void
    {
        $this->initializeStorageDirectory(dirname($this->path));

        $records = array_map(
            static fn (LogEntry $entry): array => [
                'id' => $entry->id(),
                'title' => $entry->title(),
                'content' => $entry->content(),
                'recorded_at' => $entry->recordedAt()->format(DateTimeInterface::ATOM),
                'ended_at' => $entry->endedAt()?->format(DateTimeInterface::ATOM),
                'context_id' => $entry->contextId(),
            ],
            $entries
        );

        try {
            $json = json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new StorageFailure('Could not encode Log Entries as JSON: ' . $exception->getMessage(), 0, $exception);
        }

        if (file_put_contents($this->path, $json . "\n", LOCK_EX) === false) {
            throw new StorageFailure("Could not write JSON storage file: {$this->path}");
        }
    }

    /**
     * @param array<string, mixed> $record
     */
    private function assertExpectedRecordKeys(array $record, string $source): void
    {
        $expectedKeys = ['id', 'title', 'content', 'recorded_at', 'ended_at', 'context_id'];
        $actualKeys = array_keys($record);
        sort($actualKeys);
        sort($expectedKeys);

        if ($actualKeys !== $expectedKeys) {
            throw new StorageFailure("Invalid {$source}: expected fields id, title, content, recorded_at, ended_at, context_id.");
        }

        if (!is_int($record['id']) || $record['id'] < 1) {
            throw new StorageFailure("Invalid {$source}: id must be a positive integer.");
        }

        if (!is_string($record['title']) || trim($record['title']) === '') {
            throw new StorageFailure("Invalid {$source}: title must be a non-empty string.");
        }

        if ($record['content'] !== null && !is_string($record['content'])) {
            throw new StorageFailure("Invalid {$source}: content must be a string or null.");
        }

        if (!is_string($record['recorded_at'])) {
            throw new StorageFailure("Invalid {$source}: recorded_at must be an ISO 8601 string.");
        }

        if ($record['ended_at'] !== null && !is_string($record['ended_at'])) {
            throw new StorageFailure("Invalid {$source}: ended_at must be an ISO 8601 string or null.");
        }

        if ($record['context_id'] !== null && (!is_int($record['context_id']) || $record['context_id'] < 1)) {
            throw new StorageFailure("Invalid {$source}: context_id must be a positive integer or null.");
        }
    }

    private function parseStoredDateTime(string $value, string $fieldName): DateTimeImmutable
    {
        $dateTime = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);

        if (!$dateTime instanceof DateTimeImmutable || !$this->dateParseSucceeded() || $dateTime->format(DateTimeInterface::ATOM) !== $value) {
            throw new StorageFailure("Stored {$fieldName} must be ISO 8601: {$value}");
        }

        return $dateTime;
    }

    private function dateParseSucceeded(): bool
    {
        $errors = DateTimeImmutable::getLastErrors();

        return $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
    }

    private function initializeStorageFile(string $contents): void
    {
        $this->initializeStorageDirectory(dirname($this->path));

        if (file_exists($this->path)) {
            return;
        }

        if (file_put_contents($this->path, $contents, LOCK_EX) === false) {
            throw new StorageFailure("Could not initialize JSON storage file: {$this->path}");
        }
    }

    private function initializeStorageDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (file_exists($directory)) {
            throw new StorageFailure("Storage path exists but is not a directory: {$directory}");
        }

        if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new StorageFailure("Could not create storage directory: {$directory}");
        }
    }

    private static function isListArray(array $items): bool
    {
        return $items === [] || array_keys($items) === range(0, count($items) - 1);
    }
}
