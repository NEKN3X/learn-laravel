<?php

declare(strict_types=1);

namespace TimelineCli\Application;

use TimelineCli\Domain\LogEntry;

final class LogEntryReview
{
    /**
     * @param list<LogEntry> $entries
     * @param array<int, string> $contextNamesById
     */
    public function __construct(
        private array $entries,
        private array $contextNamesById
    ) {
    }

    /** @return list<LogEntry> */
    public function entries(): array
    {
        return $this->entries;
    }

    public function contextNameFor(int $contextId): ?string
    {
        return $this->contextNamesById[$contextId] ?? null;
    }
}
