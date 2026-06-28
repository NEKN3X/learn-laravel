<?php

declare(strict_types=1);

configure_timezone_from_environment();

$command = $argv[1] ?? null;

if ($command === null) {
    fail("Missing command.\n\n" . usage());
}

switch ($command) {
    case 'log:add':
        add_log_entry(array_slice($argv, 2));
        break;

    case 'log:list':
        require_no_arguments($command, array_slice($argv, 2));
        list_log_entries(load_log_entries());
        break;

    case 'log:today':
        require_no_arguments($command, array_slice($argv, 2));
        list_todays_log_entries(load_log_entries());
        break;

    default:
        fail("Unknown command: {$command}\n\n" . usage());
}

function add_log_entry(array $args): void
{
    if (count($args) === 0 || trim($args[0]) === '') {
        fail("Missing required title.\n\nUsage: php bin/app.php log:add \"<title>\" [--content \"<content>\"]");
    }

    $title = trim($args[0]);
    $content = null;

    for ($index = 1; $index < count($args); $index++) {
        $arg = $args[$index];

        if ($arg === '--content') {
            if (!array_key_exists($index + 1, $args)) {
                fail('Missing value for --content.');
            }

            $content = $args[$index + 1];
            $index++;
            continue;
        }

        fail("Unknown option or extra argument: {$arg}");
    }

    $entries = load_log_entries();
    $entry = [
        'id' => next_log_entry_id($entries),
        'title' => $title,
        'content' => $content,
        'recorded_at' => date('c'),
        'ended_at' => null,
        'context_id' => null,
    ];

    $entries[] = $entry;
    save_log_entries($entries);

    echo "Added Log Entry #{$entry['id']}: {$entry['title']}\n";
}

function list_log_entries(array $entries): void
{
    print_log_entries(sort_log_entries_by_recorded_time($entries));
}

function list_todays_log_entries(array $entries): void
{
    $today = date('Y-m-d');
    $todaysEntries = array_values(array_filter($entries, function (array $entry) use ($today): bool {
        return substr((string) ($entry['recorded_at'] ?? ''), 0, 10) === $today;
    }));

    print_log_entries(sort_log_entries_by_recorded_time($todaysEntries));
}

function print_log_entries(array $entries): void
{
    if (count($entries) === 0) {
        echo "No Log Entries found.\n";
        return;
    }

    foreach ($entries as $entry) {
        $line = "#{$entry['id']} {$entry['recorded_at']}";

        if (!empty($entry['ended_at'])) {
            $line .= " -> {$entry['ended_at']}";
        }

        if (!empty($entry['context_id'])) {
            $line .= " [Context #{$entry['context_id']}]";
        }

        $line .= " {$entry['title']}";
        echo $line . "\n";
    }
}

function load_log_entries(): array
{
    $path = log_entries_path();
    initialize_storage_file($path);

    $json = file_get_contents($path);
    if ($json === false) {
        fail("Could not read JSON storage file: {$path}");
    }

    $entries = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        fail('Storage file contains broken JSON: ' . $path . ' (' . json_last_error_msg() . '). Fix the file before running this command.');
    }

    if (!is_array($entries) || !is_list_array($entries)) {
        fail("Storage file must contain a JSON array of Log Entries: {$path}");
    }

    return $entries;
}

function save_log_entries(array $entries): void
{
    $path = log_entries_path();
    initialize_storage_directory(dirname($path));

    $json = json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        fail('Could not encode Log Entries as JSON: ' . json_last_error_msg());
    }

    if (file_put_contents($path, $json . "\n", LOCK_EX) === false) {
        fail("Could not write JSON storage file: {$path}");
    }
}

function initialize_storage_file(string $path): void
{
    initialize_storage_directory(dirname($path));

    if (file_exists($path)) {
        return;
    }

    if (file_put_contents($path, "[]\n", LOCK_EX) === false) {
        fail("Could not initialize JSON storage file: {$path}");
    }
}

function initialize_storage_directory(string $directory): void
{
    if (is_dir($directory)) {
        return;
    }

    if (file_exists($directory)) {
        fail("Storage path exists but is not a directory: {$directory}");
    }

    if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
        fail("Could not create storage directory: {$directory}");
    }
}

function log_entries_path(): string
{
    return dirname(__DIR__) . '/data/log_entries.json';
}

function next_log_entry_id(array $entries): int
{
    $maxId = 0;

    foreach ($entries as $entry) {
        $id = $entry['id'] ?? 0;
        if (is_int($id) && $id > $maxId) {
            $maxId = $id;
        }
    }

    return $maxId + 1;
}

function sort_log_entries_by_recorded_time(array $entries): array
{
    usort($entries, function (array $a, array $b): int {
        return strcmp((string) ($a['recorded_at'] ?? ''), (string) ($b['recorded_at'] ?? ''));
    });

    return $entries;
}

function require_no_arguments(string $command, array $args): void
{
    if (count($args) > 0) {
        fail("Command {$command} does not accept arguments.");
    }
}

function is_list_array(array $items): bool
{
    return $items === [] || array_keys($items) === range(0, count($items) - 1);
}

function configure_timezone_from_environment(): void
{
    $timezone = getenv('TZ');

    if (!is_string($timezone) || trim($timezone) === '') {
        return;
    }

    if (in_array($timezone, timezone_identifiers_list(), true)) {
        date_default_timezone_set($timezone);
    }
}

function usage(): string
{
    return implode("\n", [
        'Usage:',
        '  php bin/app.php log:add "<title>" [--content "<content>"]',
        '  php bin/app.php log:list',
        '  php bin/app.php log:today',
    ]);
}

function fail(string $message): void
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}
