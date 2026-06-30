<?php

declare(strict_types=1);

namespace TimelineCli\Console;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use TimelineCli\Application\Exception\ContextNotFound;
use TimelineCli\Application\Exception\LogEntryNotFound;
use TimelineCli\Application\LogEntryChanges;
use TimelineCli\Application\LogEntryQuery;
use TimelineCli\Application\LogEntryReview;
use TimelineCli\Application\LogEntryService;
use TimelineCli\Domain\LogEntry;
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
     */
    public function edit(array $args): void
    {
        $parsed = $this->parseArguments(
            'log:edit',
            $args,
            ['--title', '--content', '--recorded-at', '--ended-at', '--context'],
            ['--clear-content', '--clear-ended-at', '--no-context']
        );
        $this->requireExactlyOnePositional('log:edit', $parsed['positionals'], 'Log Entry ID');

        if (count($parsed['options']) === 0) {
            throw new LogEntryCommandFailed('Command log:edit requires at least one change option.');
        }

        $this->requireNotMutuallyExclusive($parsed['options'], '--content', '--clear-content');
        $this->requireNotMutuallyExclusive($parsed['options'], '--ended-at', '--clear-ended-at');
        $this->requireNotMutuallyExclusive($parsed['options'], '--context', '--no-context');

        $id = $this->parseLogEntryId($parsed['positionals'][0]);
        $changes = new LogEntryChanges();

        if (array_key_exists('--title', $parsed['options'])) {
            $changes->changeTitle($this->normalizeTitle((string) $parsed['options']['--title'], 'Title cannot be empty.'));
        }

        if (array_key_exists('--content', $parsed['options'])) {
            $changes->changeContent($this->normalizeContentInput((string) $parsed['options']['--content']));
        }

        if (array_key_exists('--clear-content', $parsed['options'])) {
            $changes->changeContent(null);
        }

        if (array_key_exists('--recorded-at', $parsed['options'])) {
            $changes->changeRecordedTime($this->parseExplicitDateTime((string) $parsed['options']['--recorded-at'], '--recorded-at'));
        }

        if (array_key_exists('--ended-at', $parsed['options'])) {
            $changes->changeEndTime($this->parseExplicitDateTime((string) $parsed['options']['--ended-at'], '--ended-at'));
        }

        if (array_key_exists('--clear-ended-at', $parsed['options'])) {
            $changes->changeEndTime(null);
        }

        if (array_key_exists('--context', $parsed['options'])) {
            $changes->assignContext($this->normalizeContextName((string) $parsed['options']['--context']));
        }

        if (array_key_exists('--no-context', $parsed['options'])) {
            $changes->clearContext();
        }

        try {
            $this->entries->edit($id, $changes);
        } catch (InvalidContext | InvalidLogEntry | ContextNotFound | LogEntryNotFound | StorageFailure $exception) {
            throw new LogEntryCommandFailed($exception->getMessage(), 0, $exception);
        }

        echo "Updated Log Entry #{$id}.\n";
    }

    /**
     * @param list<string> $args
     */
    public function listEntries(array $args): void
    {
        $query = $this->parseLogEntryQueryOptions('log:list', $args, true, true);

        try {
            $review = $this->entries->review($query);
        } catch (InvalidContext | ContextNotFound | StorageFailure $exception) {
            throw new LogEntryCommandFailed($exception->getMessage(), 0, $exception);
        }

        $this->printLogEntries($review);
    }

    /**
     * @param list<string> $args
     */
    public function today(array $args): void
    {
        $query = $this->parseLogEntryQueryOptions('log:today', $args, false, true);

        try {
            $review = $this->entries->today($query);
        } catch (InvalidContext | ContextNotFound | StorageFailure $exception) {
            throw new LogEntryCommandFailed($exception->getMessage(), 0, $exception);
        }

        $this->printLogEntries($review);
    }

    /**
     * @param list<string> $args
     */
    public function exportCsv(array $args): void
    {
        $parsed = $this->parseArguments(
            'log:export-csv',
            $args,
            ['--date', '--from', '--to', '--context', '--output'],
            ['--no-context']
        );
        $this->requireNoPositionals('log:export-csv', $parsed['positionals']);

        $query = $this->buildLogEntryQuery('log:export-csv', $parsed['options'], true, false);

        try {
            $review = $this->entries->export($query);
        } catch (InvalidContext | ContextNotFound | StorageFailure $exception) {
            throw new LogEntryCommandFailed($exception->getMessage(), 0, $exception);
        }

        $csv = $this->renderLogEntriesCsv($review);

        if (array_key_exists('--output', $parsed['options'])) {
            $this->writeCsvFile((string) $parsed['options']['--output'], $csv);
            return;
        }

        echo $csv;
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
     * @param list<string> $positionals
     */
    private function requireNoPositionals(string $command, array $positionals): void
    {
        if (count($positionals) > 0) {
            throw new LogEntryCommandFailed("Command {$command} does not accept positional arguments.");
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

    /**
     * @param list<string> $args
     */
    private function parseLogEntryQueryOptions(string $command, array $args, bool $allowDateFilters, bool $allowOrder): LogEntryQuery
    {
        $valueOptions = ['--context'];

        if ($allowDateFilters) {
            $valueOptions[] = '--date';
            $valueOptions[] = '--from';
            $valueOptions[] = '--to';
        }

        if ($allowOrder) {
            $valueOptions[] = '--order';
        }

        $parsed = $this->parseArguments($command, $args, $valueOptions, ['--no-context']);
        $this->requireNoPositionals($command, $parsed['positionals']);

        return $this->buildLogEntryQuery($command, $parsed['options'], $allowDateFilters, $allowOrder);
    }

    /**
     * @param array<string, string|bool> $options
     */
    private function buildLogEntryQuery(
        string $command,
        array $options,
        bool $allowDateFilters,
        bool $allowOrder
    ): LogEntryQuery {
        if (!$allowDateFilters) {
            foreach (['--date', '--from', '--to'] as $option) {
                if (array_key_exists($option, $options)) {
                    throw new LogEntryCommandFailed("Unknown option for {$command}: {$option}");
                }
            }
        }

        $this->requireNotMutuallyExclusive($options, '--date', '--from');
        $this->requireNotMutuallyExclusive($options, '--date', '--to');
        $this->requireNotMutuallyExclusive($options, '--context', '--no-context');

        $date = array_key_exists('--date', $options) ? $this->parseLocalDate((string) $options['--date'], '--date') : null;
        $from = array_key_exists('--from', $options) ? $this->parseLocalDate((string) $options['--from'], '--from') : null;
        $to = array_key_exists('--to', $options) ? $this->parseLocalDate((string) $options['--to'], '--to') : null;

        if ($from !== null && $to !== null && $from > $to) {
            throw new LogEntryCommandFailed('--from must be on or before --to.');
        }

        $contextName = array_key_exists('--context', $options)
            ? $this->normalizeContextName((string) $options['--context'])
            : null;
        $noContext = array_key_exists('--no-context', $options);
        $order = 'desc';

        if ($allowOrder && array_key_exists('--order', $options)) {
            $order = strtolower(trim((string) $options['--order']));

            if ($order !== 'asc' && $order !== 'desc') {
                throw new LogEntryCommandFailed("Invalid order: {$options['--order']}. Use asc or desc.");
            }
        }

        return new LogEntryQuery($date, $from, $to, $contextName, $noContext, $order);
    }

    private function parseLocalDate(string $value, string $option): string
    {
        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (!$date instanceof DateTimeImmutable || !$this->dateParseSucceeded() || $date->format('Y-m-d') !== $value) {
            throw new LogEntryCommandFailed("Invalid date for {$option}: {$value}. Use Y-m-d.");
        }

        return $value;
    }

    private function printLogEntries(LogEntryReview $review): void
    {
        if (count($review->entries()) === 0) {
            echo "No Log Entries found.\n";
            return;
        }

        foreach ($review->entries() as $entry) {
            $line = "#{$entry->id()} {$entry->recordedAt()->format(DateTimeInterface::ATOM)}";

            if ($entry->endedAt() !== null) {
                $line .= " -> {$entry->endedAt()->format(DateTimeInterface::ATOM)}";
            }

            if ($entry->contextId() !== null) {
                $contextName = $review->contextNameFor($entry->contextId()) ?? "Context #{$entry->contextId()}";
                $line .= " [{$contextName}]";
            }

            $line .= " {$entry->title()}";
            echo $line . "\n";
        }
    }

    private function renderLogEntriesCsv(LogEntryReview $review): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new LogEntryCommandFailed('Could not open temporary stream for CSV output.');
        }

        fputcsv($stream, ['id', 'title', 'content', 'recorded_at', 'ended_at', 'context_id', 'context_name'], ',', '"', '');

        foreach ($review->entries() as $entry) {
            $this->writeLogEntryCsvRow($stream, $entry, $review);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        if ($csv === false) {
            throw new LogEntryCommandFailed('Could not read CSV output.');
        }

        return $csv;
    }

    /**
     * @param resource $stream
     */
    private function writeLogEntryCsvRow($stream, LogEntry $entry, LogEntryReview $review): void
    {
        $contextId = $entry->contextId();

        fputcsv($stream, [
            $entry->id(),
            $entry->title(),
            $entry->content() ?? '',
            $entry->recordedAt()->format(DateTimeInterface::ATOM),
            $entry->endedAt()?->format(DateTimeInterface::ATOM) ?? '',
            $contextId ?? '',
            $contextId === null ? '' : ($review->contextNameFor($contextId) ?? "Context #{$contextId}"),
        ], ',', '"', '');
    }

    private function writeCsvFile(string $path, string $csv): void
    {
        $path = trim($path);

        if ($path === '') {
            throw new LogEntryCommandFailed('Output path cannot be empty.');
        }

        $directory = dirname($path);
        if ($directory !== '.' && !is_dir($directory)) {
            throw new LogEntryCommandFailed("Output directory does not exist: {$directory}");
        }

        if (file_put_contents($path, $csv, LOCK_EX) === false) {
            throw new LogEntryCommandFailed("Could not write CSV output file: {$path}");
        }
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
