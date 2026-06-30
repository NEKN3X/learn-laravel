<?php

declare(strict_types=1);

namespace TimelineCli\Application;

use DateTimeImmutable;
use TimelineCli\Application\Exception\ContextNotFound;
use TimelineCli\Application\Exception\LogEntryNotFound;
use TimelineCli\Domain\ContextRepository;
use TimelineCli\Domain\CurrentContextStore;
use TimelineCli\Domain\Exception\InvalidContext;
use TimelineCli\Domain\Exception\InvalidLogEntry;
use TimelineCli\Domain\LogEntry;
use TimelineCli\Domain\LogEntryRepository;

final class LogEntryService
{
    public function __construct(
        private LogEntryRepository $entries,
        private ContextRepository $contexts,
        private CurrentContextStore $currentContext
    ) {
    }

    public function add(
        string $title,
        ?string $content,
        DateTimeImmutable $recordedAt,
        ?string $contextName,
        bool $useNoContext
    ): LogEntry {
        $contextId = $this->resolveContextIdForNewLogEntry($contextName, $useNoContext);
        $entry = new LogEntry($this->entries->nextId(), $title, $content, $recordedAt, null, $contextId);

        $this->entries->save($entry);

        return $entry;
    }

    public function end(int $id, DateTimeImmutable $endedAt): LogEntry
    {
        $entry = $this->entries->findById($id);

        if ($entry === null) {
            throw new LogEntryNotFound("Log Entry #{$id} was not found.");
        }

        try {
            $entry->end($endedAt);
        } catch (InvalidLogEntry $exception) {
            if (str_starts_with($exception->getMessage(), "Log Entry #{$id} ")) {
                throw $exception;
            }

            throw new InvalidLogEntry("Invalid Log Entry #{$id}: {$exception->getMessage()}", 0, $exception);
        }

        $this->entries->save($entry);

        return $entry;
    }

    private function resolveContextIdForNewLogEntry(?string $contextName, bool $useNoContext): ?int
    {
        if ($useNoContext) {
            return null;
        }

        if ($contextName !== null) {
            $contextName = trim($contextName);

            if ($contextName === '') {
                throw new InvalidContext('Context name cannot be empty.');
            }

            $context = $this->contexts->findByName($contextName);

            if ($context === null) {
                throw new ContextNotFound("Context not found: {$contextName}");
            }

            return $context->id();
        }

        $currentContextId = $this->currentContext->get();
        if ($currentContextId === null) {
            return null;
        }

        if ($this->contexts->findById($currentContextId) === null) {
            throw new ContextNotFound("Current Context not found: #{$currentContextId}");
        }

        return $currentContextId;
    }
}
