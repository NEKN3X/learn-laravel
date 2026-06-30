<?php

declare(strict_types=1);

namespace TimelineCli\Domain;

use DateTimeImmutable;
use TimelineCli\Domain\Exception\InvalidLogEntry;

final class LogEntry
{
    public function __construct(
        private int $id,
        private string $title,
        private ?string $content,
        private DateTimeImmutable $recordedAt,
        private ?DateTimeImmutable $endedAt,
        private ?int $contextId
    ) {
        if ($this->id < 1) {
            throw new InvalidLogEntry('id must be a positive integer.');
        }

        $this->rename($title);
        $this->changeContent($content);
        $this->assignContext($contextId);
        $this->setEndTime($endedAt);
    }

    public function id(): int
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function content(): ?string
    {
        return $this->content;
    }

    public function recordedAt(): DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function endedAt(): ?DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function contextId(): ?int
    {
        return $this->contextId;
    }

    public function rename(string $title): void
    {
        $title = trim($title);

        if ($title === '') {
            throw new InvalidLogEntry('title must be a non-empty string.');
        }

        $this->title = $title;
    }

    public function changeContent(?string $content): void
    {
        $this->content = $content;
    }

    public function changeRecordedTime(DateTimeImmutable $recordedAt): void
    {
        if ($this->endedAt !== null && $this->endedAt < $recordedAt) {
            throw new InvalidLogEntry('ended_at cannot be earlier than recorded_at.');
        }

        $this->recordedAt = $recordedAt;
    }

    public function end(DateTimeImmutable $endedAt): void
    {
        if ($this->endedAt !== null) {
            throw new InvalidLogEntry("Log Entry #{$this->id} already has an End Time.");
        }

        $this->setEndTime($endedAt);
    }

    public function clearEndTime(): void
    {
        $this->endedAt = null;
    }

    public function assignContext(?int $contextId): void
    {
        if ($contextId !== null && $contextId < 1) {
            throw new InvalidLogEntry('context_id must be a positive integer or null.');
        }

        $this->contextId = $contextId;
    }

    private function setEndTime(?DateTimeImmutable $endedAt): void
    {
        if ($endedAt !== null && $endedAt < $this->recordedAt) {
            throw new InvalidLogEntry('ended_at cannot be earlier than recorded_at.');
        }

        $this->endedAt = $endedAt;
    }
}
