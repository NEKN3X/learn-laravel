<?php

declare(strict_types=1);

namespace TimelineCli\Infrastructure;

use JsonException;
use TimelineCli\Domain\Context;
use TimelineCli\Domain\ContextRepository;
use TimelineCli\Domain\Exception\InvalidContext;

final class JsonContextRepository implements ContextRepository
{
    public function __construct(private string $path)
    {
    }

    public function nextId(): int
    {
        $maxId = 0;

        foreach ($this->all() as $context) {
            if ($context->id() > $maxId) {
                $maxId = $context->id();
            }
        }

        return $maxId + 1;
    }

    public function findById(int $id): ?Context
    {
        foreach ($this->all() as $context) {
            if ($context->id() === $id) {
                return $context;
            }
        }

        return null;
    }

    public function findByName(string $name): ?Context
    {
        $nameKey = self::nameKey($name);

        foreach ($this->all() as $context) {
            if (self::nameKey($context->name()) === $nameKey) {
                return $context;
            }
        }

        return null;
    }

    public function all(): array
    {
        $records = $this->readRecords();
        $contexts = [];
        $seenIds = [];
        $seenNames = [];

        foreach ($records as $index => $record) {
            if (!is_array($record) || self::isListArray($record)) {
                throw new StorageFailure("Invalid Context record at index {$index} in {$this->path}: expected object.");
            }

            $this->assertExpectedRecordKeys($record, "Context record at index {$index} in {$this->path}");

            try {
                $context = new Context($record['id'], $record['name']);
            } catch (InvalidContext $exception) {
                throw new StorageFailure(
                    "Invalid Context record at index {$index} in {$this->path}: {$exception->getMessage()}",
                    0,
                    $exception
                );
            }

            if (isset($seenIds[$context->id()])) {
                throw new StorageFailure("Duplicate Context ID in {$this->path}: #{$context->id()}");
            }

            $nameKey = self::nameKey($context->name());
            if (isset($seenNames[$nameKey])) {
                throw new StorageFailure("Duplicate Context name in {$this->path}: {$context->name()}");
            }

            $seenIds[$context->id()] = true;
            $seenNames[$nameKey] = true;
            $contexts[] = $context;
        }

        return $contexts;
    }

    public function save(Context $context): void
    {
        $contexts = $this->all();
        $saved = false;

        foreach ($contexts as $index => $existingContext) {
            if ($existingContext->id() === $context->id()) {
                $contexts[$index] = $context;
                $saved = true;
                break;
            }
        }

        if (!$saved) {
            $contexts[] = $context;
        }

        $this->writeContexts($contexts);
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
            throw new StorageFailure("Storage file must contain a JSON array of Contexts: {$this->path}");
        }

        return $records;
    }

    /**
     * @param list<Context> $contexts
     */
    private function writeContexts(array $contexts): void
    {
        $this->initializeStorageDirectory(dirname($this->path));

        $records = array_map(
            static fn (Context $context): array => [
                'id' => $context->id(),
                'name' => $context->name(),
            ],
            $contexts
        );

        try {
            $json = json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new StorageFailure('Could not encode Contexts as JSON: ' . $exception->getMessage(), 0, $exception);
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
        $expectedKeys = ['id', 'name'];
        $actualKeys = array_keys($record);
        sort($actualKeys);
        sort($expectedKeys);

        if ($actualKeys !== $expectedKeys) {
            throw new StorageFailure("Invalid {$source}: expected fields id, name.");
        }

        if (!is_int($record['id']) || $record['id'] < 1) {
            throw new StorageFailure("Invalid {$source}: id must be a positive integer.");
        }

        if (!is_string($record['name']) || trim($record['name']) === '') {
            throw new StorageFailure("Invalid {$source}: name must be a non-empty string.");
        }
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

    private static function nameKey(string $name): string
    {
        return strtolower(trim($name));
    }

    private static function isListArray(array $items): bool
    {
        return $items === [] || array_keys($items) === range(0, count($items) - 1);
    }
}
