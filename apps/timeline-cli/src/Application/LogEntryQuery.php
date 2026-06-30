<?php

declare(strict_types=1);

namespace TimelineCli\Application;

final class LogEntryQuery
{
    public function __construct(
        private ?string $date,
        private ?string $from,
        private ?string $to,
        private ?string $contextName,
        private bool $noContext,
        private string $order
    ) {
    }

    public function date(): ?string
    {
        return $this->date;
    }

    public function from(): ?string
    {
        return $this->from;
    }

    public function to(): ?string
    {
        return $this->to;
    }

    public function contextName(): ?string
    {
        return $this->contextName;
    }

    public function noContext(): bool
    {
        return $this->noContext;
    }

    public function order(): string
    {
        return $this->order;
    }
}
