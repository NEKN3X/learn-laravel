<?php

declare(strict_types=1);

namespace TimelineCli\Application;

use DateTimeImmutable;
use DateTimeInterface;
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

    public function edit(int $id, LogEntryChanges $changes): LogEntry
    {
        if (!$changes->hasChanges()) {
            throw new InvalidLogEntry('Command log:edit requires at least one change option.');
        }

        $entry = $this->entries->findById($id);

        if ($entry === null) {
            throw new LogEntryNotFound("Log Entry #{$id} was not found.");
        }

        $originalState = $this->entryState($entry);

        if ($changes->titleChanged()) {
            $entry->rename($changes->title());
        }

        if ($changes->contentChanged()) {
            $entry->changeContent($changes->content());
        }

        if ($changes->recordedTimeChanged() || $changes->endTimeChanged()) {
            $entry->changeTimes(
                $changes->recordedTimeChanged() ? $changes->recordedAt() : $entry->recordedAt(),
                $changes->endTimeChanged() ? $changes->endedAt() : $entry->endedAt()
            );
        }

        if ($changes->contextChanged()) {
            $entry->assignContext($changes->shouldClearContext() ? null : $this->resolveContextId($changes->contextName()));
        }

        if ($this->entryState($entry) === $originalState) {
            throw new InvalidLogEntry("Command log:edit did not change Log Entry #{$id}.");
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
            return $this->resolveContextId($contextName);
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

    private function resolveContextId(string $contextName): int
    {
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

    /**
     * @return array{id: int, title: string, content: ?string, recorded_at: string, ended_at: ?string, context_id: ?int}
     */
    private function entryState(LogEntry $entry): array
    {
        return [
            'id' => $entry->id(),
            'title' => $entry->title(),
            'content' => $entry->content(),
            'recorded_at' => $entry->recordedAt()->format(DateTimeInterface::ATOM),
            'ended_at' => $entry->endedAt()?->format(DateTimeInterface::ATOM),
            'context_id' => $entry->contextId(),
        ];
    }
}
