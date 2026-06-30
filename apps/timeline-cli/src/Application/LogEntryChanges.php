<?php

declare(strict_types=1);

namespace TimelineCli\Application;

use DateTimeImmutable;
use LogicException;

final class LogEntryChanges
{
    private bool $titleChanged = false;
    private string $title = '';
    private bool $contentChanged = false;
    private ?string $content = null;
    private bool $recordedTimeChanged = false;
    private ?DateTimeImmutable $recordedAt = null;
    private bool $endTimeChanged = false;
    private ?DateTimeImmutable $endedAt = null;
    private bool $contextChanged = false;
    private ?string $contextName = null;
    private bool $clearContext = false;

    public function changeTitle(string $title): void
    {
        $this->titleChanged = true;
        $this->title = $title;
    }

    public function titleChanged(): bool
    {
        return $this->titleChanged;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function changeContent(?string $content): void
    {
        $this->contentChanged = true;
        $this->content = $content;
    }

    public function contentChanged(): bool
    {
        return $this->contentChanged;
    }

    public function content(): ?string
    {
        return $this->content;
    }

    public function changeRecordedTime(DateTimeImmutable $recordedAt): void
    {
        $this->recordedTimeChanged = true;
        $this->recordedAt = $recordedAt;
    }

    public function recordedTimeChanged(): bool
    {
        return $this->recordedTimeChanged;
    }

    public function recordedAt(): DateTimeImmutable
    {
        if ($this->recordedAt === null) {
            throw new LogicException('Recorded Time was not changed.');
        }

        return $this->recordedAt;
    }

    public function changeEndTime(?DateTimeImmutable $endedAt): void
    {
        $this->endTimeChanged = true;
        $this->endedAt = $endedAt;
    }

    public function endTimeChanged(): bool
    {
        return $this->endTimeChanged;
    }

    public function endedAt(): ?DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function assignContext(string $contextName): void
    {
        $this->contextChanged = true;
        $this->contextName = $contextName;
        $this->clearContext = false;
    }

    public function clearContext(): void
    {
        $this->contextChanged = true;
        $this->contextName = null;
        $this->clearContext = true;
    }

    public function contextChanged(): bool
    {
        return $this->contextChanged;
    }

    public function contextName(): string
    {
        if ($this->contextName === null) {
            throw new LogicException('Context assignment was not changed.');
        }

        return $this->contextName;
    }

    public function shouldClearContext(): bool
    {
        return $this->clearContext;
    }

    public function hasChanges(): bool
    {
        return $this->titleChanged
            || $this->contentChanged
            || $this->recordedTimeChanged
            || $this->endTimeChanged
            || $this->contextChanged;
    }
}
