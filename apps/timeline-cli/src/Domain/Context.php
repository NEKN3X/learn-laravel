<?php

declare(strict_types=1);

namespace TimelineCli\Domain;

use TimelineCli\Domain\Exception\InvalidContext;

final class Context
{
    public function __construct(
        private int $id,
        private string $name
    ) {
        if ($this->id < 1) {
            throw new InvalidContext('id must be a positive integer.');
        }

        $this->rename($name);
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidContext('Context name cannot be empty.');
        }

        $this->name = $name;
    }
}
