<?php

declare(strict_types=1);

namespace TimelineCli\Domain;

interface CurrentContextStore
{
    public function get(): ?int;

    public function set(?int $contextId): void;
}
