<?php

declare(strict_types=1);

namespace TimelineCli\Console;

use TimelineCli\Application\ContextService;
use TimelineCli\Application\Exception\ContextNotFound;
use TimelineCli\Application\Exception\DuplicateContextName;
use TimelineCli\Domain\Exception\InvalidContext;
use TimelineCli\Infrastructure\StorageFailure;

final class ContextConsole
{
    public function __construct(private ContextService $contexts)
    {
    }

    /**
     * @param list<string> $args
     */
    public function add(array $args): void
    {
        $parsed = $this->parseArguments('context:add', $args, [], []);
        $this->requireExactlyOnePositional('context:add', $parsed['positionals'], 'Context name');

        try {
            $context = $this->contexts->add($parsed['positionals'][0]);
        } catch (InvalidContext | DuplicateContextName | StorageFailure $exception) {
            throw new ContextCommandFailed($exception->getMessage(), 0, $exception);
        }

        echo "Added Context #{$context->id()}: {$context->name()}\n";
    }

    /**
     * @param list<string> $args
     */
    public function list(array $args): void
    {
        $this->requireNoArguments('context:list', $args);

        try {
            $contextList = $this->contexts->list();
        } catch (StorageFailure $exception) {
            throw new ContextCommandFailed($exception->getMessage(), 0, $exception);
        }

        if (count($contextList->contexts()) === 0) {
            echo "No Contexts found.\n";
            return;
        }

        foreach ($contextList->contexts() as $context) {
            $line = "#{$context->id()} {$context->name()}";

            if ($context->id() === $contextList->currentContextId()) {
                $line .= ' (current)';
            }

            echo $line . "\n";
        }
    }

    /**
     * @param list<string> $args
     */
    public function switch(array $args): void
    {
        $parsed = $this->parseArguments('context:switch', $args, [], []);
        $this->requireExactlyOnePositional('context:switch', $parsed['positionals'], 'Context name');

        try {
            $context = $this->contexts->switchTo($parsed['positionals'][0]);
        } catch (InvalidContext | ContextNotFound | StorageFailure $exception) {
            throw new ContextCommandFailed($exception->getMessage(), 0, $exception);
        }

        echo "Switched Current Context to {$context->name()}.\n";
    }

    /**
     * @param list<string> $args
     */
    public function clear(array $args): void
    {
        $this->requireNoArguments('context:clear', $args);

        try {
            $this->contexts->clear();
        } catch (StorageFailure $exception) {
            throw new ContextCommandFailed($exception->getMessage(), 0, $exception);
        }

        echo "Cleared Current Context.\n";
    }

    /**
     * @param list<string> $args
     * @param list<string> $valueOptions
     * @param list<string> $flagOptions
     * @return array{positionals: list<string>, options: array<string, string|bool>}
     */
    private function parseArguments(string $command, array $args, array $valueOptions, array $flagOptions): array
    {
        $positionals = [];
        $options = [];
        $valueOptionsByName = array_fill_keys($valueOptions, true);
        $flagOptionsByName = array_fill_keys($flagOptions, true);

        for ($index = 0; $index < count($args); $index++) {
            $arg = $args[$index];

            if (!is_string($arg)) {
                throw new ContextCommandFailed("Invalid argument for {$command}.");
            }

            if (!str_starts_with($arg, '--')) {
                $positionals[] = $arg;
                continue;
            }

            if (!isset($valueOptionsByName[$arg]) && !isset($flagOptionsByName[$arg])) {
                throw new ContextCommandFailed("Unknown option for {$command}: {$arg}");
            }

            if (array_key_exists($arg, $options)) {
                throw new ContextCommandFailed("Duplicate option for {$command}: {$arg}");
            }

            if (isset($flagOptionsByName[$arg])) {
                $options[$arg] = true;
                continue;
            }

            if (!array_key_exists($index + 1, $args) || str_starts_with((string) $args[$index + 1], '--')) {
                throw new ContextCommandFailed("Missing value for {$arg}.");
            }

            $options[$arg] = $args[$index + 1];
            $index++;
        }

        return [
            'positionals' => $positionals,
            'options' => $options,
        ];
    }

    /**
     * @param list<string> $positionals
     */
    private function requireExactlyOnePositional(string $command, array $positionals, string $name): void
    {
        if (count($positionals) === 0 || trim((string) $positionals[0]) === '') {
            throw new ContextCommandFailed("Missing required {$name}.\n\n" . $this->commandUsage($command));
        }

        if (count($positionals) > 1) {
            throw new ContextCommandFailed("Command {$command} accepts exactly one {$name}.");
        }
    }

    /**
     * @param list<string> $args
     */
    private function requireNoArguments(string $command, array $args): void
    {
        foreach ($args as $arg) {
            if (is_string($arg) && str_starts_with($arg, '--')) {
                throw new ContextCommandFailed("Unknown option for {$command}: {$arg}");
            }
        }

        if (count($args) > 0) {
            throw new ContextCommandFailed("Command {$command} does not accept arguments.");
        }
    }

    private function commandUsage(string $command): string
    {
        $lines = [
            'context:add' => 'Usage: php bin/app.php context:add <name>',
            'context:switch' => 'Usage: php bin/app.php context:switch <name>',
        ];

        return $lines[$command] ?? $this->usage();
    }

    private function usage(): string
    {
        return implode("\n", [
            'Usage:',
            '  php bin/app.php log:add "<title>" [--content "<content>"] [--recorded-at "<datetime>"] [--context <name>|--no-context]',
            '  php bin/app.php log:end <id> [--ended-at "<datetime>"]',
            '  php bin/app.php log:edit <id> [--title "<title>"] [--content "<content>"|--clear-content] [--recorded-at "<datetime>"] [--ended-at "<datetime>"|--clear-ended-at] [--context <name>|--no-context]',
            '  php bin/app.php log:list [--date <Y-m-d>|--from <Y-m-d> [--to <Y-m-d>]|--to <Y-m-d>] [--context <name>|--no-context] [--order asc|desc]',
            '  php bin/app.php log:today [--context <name>|--no-context] [--order asc|desc]',
            '  php bin/app.php log:export-csv [--date <Y-m-d>|--from <Y-m-d> [--to <Y-m-d>]|--to <Y-m-d>] [--context <name>|--no-context] [--output <path>]',
            '  php bin/app.php context:add <name>',
            '  php bin/app.php context:list',
            '  php bin/app.php context:switch <name>',
            '  php bin/app.php context:clear',
        ]);
    }
}
