<?php

declare(strict_types=1);

namespace TimelineCli\Console;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use TimelineCli\Application\Exception\ContextNotFound;
use TimelineCli\Application\Exception\LogEntryNotFound;
use TimelineCli\Application\LogEntryService;
use TimelineCli\Domain\Exception\InvalidContext;
use TimelineCli\Domain\Exception\InvalidLogEntry;
use TimelineCli\Infrastructure\StorageFailure;

final class LogEntryConsole
{
    public function __construct(private LogEntryService $entries)
    {
    }

    /**
     * @param list<string> $args
     */
    public function add(array $args): void
    {
        $parsed = $this->parseArguments(
            'log:add',
            $args,
            ['--content', '--context', '--recorded-at'],
            ['--no-context']
        );
        $this->requireExactlyOnePositional('log:add', $parsed['positionals'], 'title');
        $this->requireNotMutuallyExclusive($parsed['options'], '--context', '--no-context');

        $title = $this->normalizeTitle($parsed['positionals'][0], 'Missing required title.');
        $content = array_key_exists('--content', $parsed['options'])
            ? $this->normalizeContentInput((string) $parsed['options']['--content'])
            : null;
        $contextName = array_key_exists('--context', $parsed['options'])
            ? $this->normalizeContextName((string) $parsed['options']['--context'])
            : null;
        $useNoContext = array_key_exists('--no-context', $parsed['options']);
        $recordedAt = array_key_exists('--recorded-at', $parsed['options'])
            ? $this->parseExplicitDateTime((string) $parsed['options']['--recorded-at'], '--recorded-at')
            : new DateTimeImmutable();

        try {
            $entry = $this->entries->add($title, $content, $recordedAt, $contextName, $useNoContext);
        } catch (InvalidContext | InvalidLogEntry | ContextNotFound | StorageFailure $exception) {
            throw new LogEntryCommandFailed($exception->getMessage(), 0, $exception);
        }

        echo "Added Log Entry #{$entry->id()}: {$entry->title()}\n";
    }

    /**
     * @param list<string> $args
     */
    public function end(array $args): void
    {
        $parsed = $this->parseArguments('log:end', $args, ['--ended-at'], []);
        $this->requireExactlyOnePositional('log:end', $parsed['positionals'], 'Log Entry ID');

        $id = $this->parseLogEntryId($parsed['positionals'][0]);
        $endedAt = array_key_exists('--ended-at', $parsed['options'])
            ? $this->parseExplicitDateTime((string) $parsed['options']['--ended-at'], '--ended-at')
            : new DateTimeImmutable();

        try {
            $entry = $this->entries->end($id, $endedAt);
        } catch (InvalidLogEntry | LogEntryNotFound | StorageFailure $exception) {
            throw new LogEntryCommandFailed($exception->getMessage(), 0, $exception);
        }

        echo "Ended Log Entry #{$id}: {$entry->endedAt()?->format(DateTimeInterface::ATOM)}\n";
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
                throw new LogEntryCommandFailed("Invalid argument for {$command}.");
            }

            if (!str_starts_with($arg, '--')) {
                $positionals[] = $arg;
                continue;
            }

            if (!isset($valueOptionsByName[$arg]) && !isset($flagOptionsByName[$arg])) {
                throw new LogEntryCommandFailed("Unknown option for {$command}: {$arg}");
            }

            if (array_key_exists($arg, $options)) {
                throw new LogEntryCommandFailed("Duplicate option for {$command}: {$arg}");
            }

            if (isset($flagOptionsByName[$arg])) {
                $options[$arg] = true;
                continue;
            }

            if (!array_key_exists($index + 1, $args) || str_starts_with((string) $args[$index + 1], '--')) {
                throw new LogEntryCommandFailed("Missing value for {$arg}.");
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
            throw new LogEntryCommandFailed("Missing required {$name}.\n\n" . $this->commandUsage($command));
        }

        if (count($positionals) > 1) {
            throw new LogEntryCommandFailed("Command {$command} accepts exactly one {$name}.");
        }
    }

    /**
     * @param array<string, string|bool> $options
     */
    private function requireNotMutuallyExclusive(array $options, string $first, string $second): void
    {
        if (array_key_exists($first, $options) && array_key_exists($second, $options)) {
            throw new LogEntryCommandFailed("Cannot provide both {$first} and {$second}.");
        }
    }

    private function normalizeTitle(string $title, string $emptyMessage): string
    {
        $title = trim($title);

        if ($title === '') {
            throw new LogEntryCommandFailed($emptyMessage);
        }

        return $title;
    }

    private function normalizeContentInput(string $content): string
    {
        if (trim($content) === '') {
            return '';
        }

        return $content;
    }

    private function normalizeContextName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new LogEntryCommandFailed('Context name cannot be empty.');
        }

        return $name;
    }

    private function parseExplicitDateTime(string $value, string $option): DateTimeImmutable
    {
        $value = trim($value);

        if ($value === '') {
            throw new LogEntryCommandFailed("Invalid date/time for {$option}: value cannot be empty.");
        }

        $timezone = new DateTimeZone(date_default_timezone_get());
        $localDateTime = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $value, $timezone);

        if ($localDateTime instanceof DateTimeImmutable && $this->dateParseSucceeded() && $localDateTime->format('Y-m-d H:i') === $value) {
            return $localDateTime;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value) === 1) {
            $utc = new DateTimeZone('UTC');
            $isoDateTime = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, $utc);

            if ($isoDateTime instanceof DateTimeImmutable && $this->dateParseSucceeded() && $isoDateTime->format('Y-m-d\TH:i:s\Z') === $value) {
                return $isoDateTime;
            }
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $value) === 1) {
            $isoDateTime = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $value);

            if ($isoDateTime instanceof DateTimeImmutable && $this->dateParseSucceeded() && $isoDateTime->format(DateTimeInterface::ATOM) === $value) {
                return $isoDateTime;
            }
        }

        throw new LogEntryCommandFailed("Invalid date/time for {$option}: {$value}. Use Y-m-d H:i or ISO 8601.");
    }

    private function dateParseSucceeded(): bool
    {
        $errors = DateTimeImmutable::getLastErrors();

        return $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
    }

    private function parseLogEntryId(string $id): int
    {
        $id = trim($id);

        if ($id === '' || !ctype_digit($id) || (int) $id < 1) {
            throw new LogEntryCommandFailed("Invalid Log Entry ID: {$id}");
        }

        return (int) $id;
    }

    private function commandUsage(string $command): string
    {
        $lines = [
            'log:add' => 'Usage: php bin/app.php log:add "<title>" [--content "<content>"] [--recorded-at "<datetime>"] [--context <name>|--no-context]',
            'log:end' => 'Usage: php bin/app.php log:end <id> [--ended-at "<datetime>"]',
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
