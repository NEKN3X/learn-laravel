<?php

declare(strict_types=1);

namespace TimelineCli\Infrastructure;

use JsonException;
use TimelineCli\Domain\CurrentContextStore;

final class JsonCurrentContextStore implements CurrentContextStore
{
    public function __construct(private string $path)
    {
    }

    public function get(): ?int
    {
        $currentContext = $this->readCurrentContext();
        $currentContextId = $currentContext['current_context_id'];

        if ($currentContextId !== null && (!is_int($currentContextId) || $currentContextId < 1)) {
            throw new StorageFailure("Current Context ID must be a positive integer or null: {$this->path}");
        }

        return $currentContextId;
    }

    public function set(?int $contextId): void
    {
        if ($contextId !== null && $contextId < 1) {
            throw new StorageFailure('Current Context ID must be a positive integer or null.');
        }

        $this->initializeStorageDirectory(dirname($this->path));

        try {
            $json = json_encode(['current_context_id' => $contextId], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new StorageFailure('Could not encode Current Context as JSON: ' . $exception->getMessage(), 0, $exception);
        }

        if (file_put_contents($this->path, $json . "\n", LOCK_EX) === false) {
            throw new StorageFailure("Could not write JSON storage file: {$this->path}");
        }
    }

    /**
     * @return array{current_context_id: mixed}
     */
    private function readCurrentContext(): array
    {
        $this->initializeStorageFile("{\n    \"current_context_id\": null\n}\n");

        $json = file_get_contents($this->path);
        if ($json === false) {
            throw new StorageFailure("Could not read JSON storage file: {$this->path}");
        }

        try {
            $currentContext = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new StorageFailure(
                'Storage file contains broken JSON: ' . $this->path . ' (' . $exception->getMessage() . '). Fix the file before running this command.',
                0,
                $exception
            );
        }

        if (!is_array($currentContext) || $this->isListArray($currentContext) || array_keys($currentContext) !== ['current_context_id']) {
            throw new StorageFailure("Storage file must contain a Current Context object: {$this->path}");
        }

        return $currentContext;
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

    private function isListArray(array $items): bool
    {
        return $items === [] || array_keys($items) === range(0, count($items) - 1);
    }
}
