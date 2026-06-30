<?php

declare(strict_types=1);

namespace TimelineCli\Domain;

interface LogEntryRepository
{
    public function nextId(): int;

    public function findById(int $id): ?LogEntry;

    /** @return list<LogEntry> */
    public function all(): array;

    public function save(LogEntry $entry): void;
}
