<?php

declare(strict_types=1);

namespace TimelineCli\Application;

use TimelineCli\Application\Exception\ContextNotFound;
use TimelineCli\Application\Exception\DuplicateContextName;
use TimelineCli\Domain\Context;
use TimelineCli\Domain\ContextRepository;
use TimelineCli\Domain\CurrentContextStore;
use TimelineCli\Domain\Exception\InvalidContext;

final class ContextService
{
    public function __construct(
        private ContextRepository $contexts,
        private CurrentContextStore $currentContext
    ) {
    }

    public function add(string $name): Context
    {
        $context = new Context($this->contexts->nextId(), $name);

        if ($this->contexts->findByName($context->name()) !== null) {
            throw new DuplicateContextName("Context already exists: {$context->name()}");
        }

        $this->contexts->save($context);

        return $context;
    }

    public function list(): ContextList
    {
        return new ContextList($this->contexts->all(), $this->currentContext->get());
    }

    public function switchTo(string $name): Context
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidContext('Context name cannot be empty.');
        }

        $context = $this->contexts->findByName($name);

        if ($context === null) {
            throw new ContextNotFound('Context not found: ' . $name);
        }

        $this->currentContext->set($context->id());

        return $context;
    }

    public function clear(): void
    {
        $this->currentContext->get();
        $this->currentContext->set(null);
    }
}
