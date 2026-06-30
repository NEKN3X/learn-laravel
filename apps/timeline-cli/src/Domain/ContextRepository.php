<?php

declare(strict_types=1);

namespace TimelineCli\Domain;

interface ContextRepository
{
    public function nextId(): int;

    public function findById(int $id): ?Context;

    public function findByName(string $name): ?Context;

    /** @return list<Context> */
    public function all(): array;

    public function save(Context $context): void;
}
