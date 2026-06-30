<?php

declare(strict_types=1);

namespace TimelineCli\Application;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use TimelineCli\Application\Exception\ContextNotFound;
use TimelineCli\Application\Exception\LogEntryNotFound;
use TimelineCli\Domain\Context;
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

    public function review(LogEntryQuery $query): LogEntryReview
    {
        $entries = $this->filterLogEntries($this->entries->all(), $query);
        $entries = $this->sortLogEntriesByRecordedTime($entries, $query->order());

        return new LogEntryReview($entries, $this->contextNamesById());
    }

    public function today(LogEntryQuery $query): LogEntryReview
    {
        $today = new LogEntryQuery(
            (new DateTimeImmutable())->format('Y-m-d'),
            null,
            null,
            $query->contextName(),
            $query->noContext(),
            $query->order()
        );

        return $this->review($today);
    }

    public function export(LogEntryQuery $query): LogEntryReview
    {
        $exportQuery = new LogEntryQuery(
            $query->date(),
            $query->from(),
            $query->to(),
            $query->contextName(),
            $query->noContext(),
            'asc'
        );

        return $this->review($exportQuery);
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
     * @param list<LogEntry> $entries
     * @return list<LogEntry>
     */
    private function filterLogEntries(array $entries, LogEntryQuery $query): array
    {
        $contextId = $query->contextName() === null ? null : $this->resolveContextId($query->contextName());

        return array_values(array_filter($entries, function (LogEntry $entry) use ($query, $contextId): bool {
            $recordedDate = $this->localRecordedDate($entry);

            if ($query->date() !== null && $recordedDate !== $query->date()) {
                return false;
            }

            if ($query->from() !== null && $recordedDate < $query->from()) {
                return false;
            }

            if ($query->to() !== null && $recordedDate > $query->to()) {
                return false;
            }

            if ($contextId !== null && $entry->contextId() !== $contextId) {
                return false;
            }

            if ($query->noContext() && $entry->contextId() !== null) {
                return false;
            }

            return true;
        }));
    }

    private function localRecordedDate(LogEntry $entry): string
    {
        return $entry->recordedAt()
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->format('Y-m-d');
    }

    /**
     * @param list<LogEntry> $entries
     * @return list<LogEntry>
     */
    private function sortLogEntriesByRecordedTime(array $entries, string $order): array
    {
        usort($entries, static function (LogEntry $a, LogEntry $b) use ($order): int {
            $comparison = $a->recordedAt() <=> $b->recordedAt();

            if ($comparison === 0) {
                $comparison = $a->id() <=> $b->id();
            }

            return $order === 'desc' ? -$comparison : $comparison;
        });

        return $entries;
    }

    /** @return array<int, string> */
    private function contextNamesById(): array
    {
        return array_reduce(
            $this->contexts->all(),
            static function (array $namesById, Context $context): array {
                $namesById[$context->id()] = $context->name();

                return $namesById;
            },
            []
        );
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
