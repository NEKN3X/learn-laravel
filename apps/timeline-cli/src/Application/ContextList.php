<?php

declare(strict_types=1);

namespace TimelineCli\Application;

use TimelineCli\Domain\Context;

final class ContextList
{
    /**
     * @param list<Context> $contexts
     */
    public function __construct(
        private array $contexts,
        private ?int $currentContextId
    ) {
    }

    /** @return list<Context> */
    public function contexts(): array
    {
        return $this->contexts;
    }

    public function currentContextId(): ?int
    {
        return $this->currentContextId;
    }
}
