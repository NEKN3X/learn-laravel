<?php

declare(strict_types=1);

namespace TimelineCli\Console;

final class ConsoleApplication
{
    public function __construct(
        private LogEntryConsole $logEntries,
        private ContextConsole $contexts
    ) {
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $command = $argv[1] ?? null;

        if ($command === null) {
            return $this->fail("Missing command.\n\n" . $this->usage());
        }

        try {
            match ($command) {
                'log:add' => $this->logEntries->add(array_slice($argv, 2)),
                'log:end' => $this->logEntries->end(array_slice($argv, 2)),
                'log:edit' => $this->logEntries->edit(array_slice($argv, 2)),
                'log:list' => $this->logEntries->listEntries(array_slice($argv, 2)),
                'log:today' => $this->logEntries->today(array_slice($argv, 2)),
                'log:export-csv' => $this->logEntries->exportCsv(array_slice($argv, 2)),
                'context:add' => $this->contexts->add(array_slice($argv, 2)),
                'context:list' => $this->contexts->list(array_slice($argv, 2)),
                'context:switch' => $this->contexts->switch(array_slice($argv, 2)),
                'context:clear' => $this->contexts->clear(array_slice($argv, 2)),
                default => throw new ConsoleCommandFailed("Unknown command: {$command}\n\n" . $this->usage()),
            };
        } catch (ConsoleCommandFailed $exception) {
            return $this->fail($exception->getMessage());
        }

        return 0;
    }

    private function fail(string $message): int
    {
        fwrite(STDERR, $message . "\n");

        return 1;
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
