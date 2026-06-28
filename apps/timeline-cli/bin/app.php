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

    case 'log:end':
        end_log_entry(array_slice($argv, 2));
        break;

    case 'log:list':
        require_no_arguments($command, array_slice($argv, 2));
        list_log_entries(load_log_entries(), load_contexts());
        break;

    case 'log:today':
        require_no_arguments($command, array_slice($argv, 2));
        list_todays_log_entries(load_log_entries(), load_contexts());
        break;

    case 'context:add':
        add_context(array_slice($argv, 2));
        break;

    case 'context:list':
        require_no_arguments($command, array_slice($argv, 2));
        list_contexts(load_contexts(), load_current_context_id());
        break;

    case 'context:switch':
        switch_current_context(array_slice($argv, 2));
        break;

    case 'context:clear':
        require_no_arguments($command, array_slice($argv, 2));
        save_current_context_id(null);
        echo "Cleared Current Context.\n";
        break;

    default:
        fail("Unknown command: {$command}\n\n" . usage());
}

function add_log_entry(array $args): void
{
    if (count($args) === 0 || trim($args[0]) === '') {
        fail("Missing required title.\n\nUsage: php bin/app.php log:add \"<title>\" [--content \"<content>\"] [--context <name>|--no-context]");
    }

    $title = trim($args[0]);
    $content = null;
    $contextName = null;
    $useNoContext = false;

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

        if ($arg === '--context') {
            if (!array_key_exists($index + 1, $args)) {
                fail('Missing value for --context.');
            }

            $contextName = trim($args[$index + 1]);
            if ($contextName === '') {
                fail('Context name cannot be empty.');
            }

            $index++;
            continue;
        }

        if ($arg === '--no-context') {
            $useNoContext = true;
            continue;
        }

        fail("Unknown option or extra argument: {$arg}");
    }

    if ($contextName !== null && $useNoContext) {
        fail('Cannot provide both --context and --no-context.');
    }

    $entries = load_log_entries();
    $contexts = load_contexts();
    $contextId = resolve_context_id_for_new_log_entry($contexts, $contextName, $useNoContext);
    $entry = [
        'id' => next_log_entry_id($entries),
        'title' => $title,
        'content' => $content,
        'recorded_at' => date('c'),
        'ended_at' => null,
        'context_id' => $contextId,
    ];

    $entries[] = $entry;
    save_log_entries($entries);

    echo "Added Log Entry #{$entry['id']}: {$entry['title']}\n";
}

function add_context(array $args): void
{
    if (count($args) === 0 || trim($args[0]) === '') {
        fail("Missing required Context name.\n\nUsage: php bin/app.php context:add <name>");
    }

    if (count($args) > 1) {
        fail('Command context:add accepts exactly one Context name.');
    }

    $name = trim($args[0]);
    $contexts = load_contexts();

    if (find_context_by_name($contexts, $name) !== null) {
        fail("Context already exists: {$name}");
    }

    $context = [
        'id' => next_context_id($contexts),
        'name' => $name,
    ];

    $contexts[] = $context;
    save_contexts($contexts);

    echo "Added Context #{$context['id']}: {$context['name']}\n";
}

function switch_current_context(array $args): void
{
    if (count($args) === 0 || trim($args[0]) === '') {
        fail("Missing required Context name.\n\nUsage: php bin/app.php context:switch <name>");
    }

    if (count($args) > 1) {
        fail('Command context:switch accepts exactly one Context name.');
    }

    $name = trim($args[0]);
    $context = find_context_by_name(load_contexts(), $name);

    if ($context === null) {
        fail("Context not found: {$name}");
    }

    save_current_context_id($context['id']);

    echo "Switched Current Context to {$context['name']}.\n";
}

function end_log_entry(array $args): void
{
    if (count($args) === 0 || trim($args[0]) === '') {
        fail("Missing required Log Entry ID.\n\nUsage: php bin/app.php log:end <id>");
    }

    if (count($args) > 1) {
        fail("Command log:end accepts exactly one Log Entry ID.");
    }

    $id = parse_log_entry_id($args[0]);
    $entries = load_log_entries();

    foreach ($entries as $index => $entry) {
        if (($entry['id'] ?? null) !== $id) {
            continue;
        }

        if (!empty($entry['ended_at'])) {
            fail("Log Entry #{$id} already has an End Time.");
        }

        $entries[$index]['ended_at'] = date('c');
        save_log_entries($entries);

        echo "Ended Log Entry #{$id}: {$entries[$index]['ended_at']}\n";
        return;
    }

    fail("Log Entry #{$id} was not found.");
}

function list_log_entries(array $entries, array $contexts): void
{
    print_log_entries(sort_log_entries_by_recorded_time($entries), $contexts);
}

function list_todays_log_entries(array $entries, array $contexts): void
{
    $today = date('Y-m-d');
    $todaysEntries = array_values(array_filter($entries, function (array $entry) use ($today): bool {
        return substr((string) ($entry['recorded_at'] ?? ''), 0, 10) === $today;
    }));

    print_log_entries(sort_log_entries_by_recorded_time($todaysEntries), $contexts);
}

function list_contexts(array $contexts, ?int $currentContextId): void
{
    if (count($contexts) === 0) {
        echo "No Contexts found.\n";
        return;
    }

    foreach ($contexts as $context) {
        $line = "#{$context['id']} {$context['name']}";

        if ($context['id'] === $currentContextId) {
            $line .= ' (current)';
        }

        echo $line . "\n";
    }
}

function print_log_entries(array $entries, array $contexts): void
{
    if (count($entries) === 0) {
        echo "No Log Entries found.\n";
        return;
    }

    $contextNamesById = context_names_by_id($contexts);

    foreach ($entries as $entry) {
        $line = "#{$entry['id']} {$entry['recorded_at']}";

        if (!empty($entry['ended_at'])) {
            $line .= " -> {$entry['ended_at']}";
        }

        if (!empty($entry['context_id'])) {
            $contextName = $contextNamesById[$entry['context_id']] ?? "Context #{$entry['context_id']}";
            $line .= " [{$contextName}]";
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

function load_contexts(): array
{
    $path = contexts_path();
    initialize_storage_file($path, "[]\n");

    $json = file_get_contents($path);
    if ($json === false) {
        fail("Could not read JSON storage file: {$path}");
    }

    $contexts = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        fail('Storage file contains broken JSON: ' . $path . ' (' . json_last_error_msg() . '). Fix the file before running this command.');
    }

    if (!is_array($contexts) || !is_list_array($contexts)) {
        fail("Storage file must contain a JSON array of Contexts: {$path}");
    }

    return $contexts;
}

function save_contexts(array $contexts): void
{
    $path = contexts_path();
    initialize_storage_directory(dirname($path));

    $json = json_encode($contexts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        fail('Could not encode Contexts as JSON: ' . json_last_error_msg());
    }

    if (file_put_contents($path, $json . "\n", LOCK_EX) === false) {
        fail("Could not write JSON storage file: {$path}");
    }
}

function load_current_context_id(): ?int
{
    $path = current_context_path();
    initialize_storage_file($path, "{\n    \"current_context_id\": null\n}\n");

    $json = file_get_contents($path);
    if ($json === false) {
        fail("Could not read JSON storage file: {$path}");
    }

    $currentContext = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        fail('Storage file contains broken JSON: ' . $path . ' (' . json_last_error_msg() . '). Fix the file before running this command.');
    }

    if (!is_array($currentContext) || is_list_array($currentContext) || !array_key_exists('current_context_id', $currentContext)) {
        fail("Storage file must contain a Current Context object: {$path}");
    }

    $currentContextId = $currentContext['current_context_id'];
    if ($currentContextId !== null && !is_int($currentContextId)) {
        fail("Current Context ID must be an integer or null: {$path}");
    }

    return $currentContextId;
}

function save_current_context_id(?int $contextId): void
{
    $path = current_context_path();
    initialize_storage_directory(dirname($path));

    $json = json_encode(['current_context_id' => $contextId], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        fail('Could not encode Current Context as JSON: ' . json_last_error_msg());
    }

    if (file_put_contents($path, $json . "\n", LOCK_EX) === false) {
        fail("Could not write JSON storage file: {$path}");
    }
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

function initialize_storage_file(string $path, string $contents = "[]\n"): void
{
    initialize_storage_directory(dirname($path));

    if (file_exists($path)) {
        return;
    }

    if (file_put_contents($path, $contents, LOCK_EX) === false) {
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

function contexts_path(): string
{
    return dirname(__DIR__) . '/data/contexts.json';
}

function current_context_path(): string
{
    return dirname(__DIR__) . '/data/current_context.json';
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

function next_context_id(array $contexts): int
{
    $maxId = 0;

    foreach ($contexts as $context) {
        $id = $context['id'] ?? 0;
        if (is_int($id) && $id > $maxId) {
            $maxId = $id;
        }
    }

    return $maxId + 1;
}

function resolve_context_id_for_new_log_entry(array $contexts, ?string $contextName, bool $useNoContext): ?int
{
    if ($useNoContext) {
        return null;
    }

    if ($contextName !== null) {
        $context = find_context_by_name($contexts, $contextName);

        if ($context === null) {
            fail("Context not found: {$contextName}");
        }

        return $context['id'];
    }

    $currentContextId = load_current_context_id();
    if ($currentContextId === null) {
        return null;
    }

    if (find_context_by_id($contexts, $currentContextId) === null) {
        fail("Current Context not found: #{$currentContextId}");
    }

    return $currentContextId;
}

function find_context_by_name(array $contexts, string $name): ?array
{
    foreach ($contexts as $context) {
        if (($context['name'] ?? null) === $name) {
            return $context;
        }
    }

    return null;
}

function find_context_by_id(array $contexts, int $id): ?array
{
    foreach ($contexts as $context) {
        if (($context['id'] ?? null) === $id) {
            return $context;
        }
    }

    return null;
}

function context_names_by_id(array $contexts): array
{
    $namesById = [];

    foreach ($contexts as $context) {
        if (!isset($context['id'], $context['name']) || !is_int($context['id']) || !is_string($context['name'])) {
            continue;
        }

        $namesById[$context['id']] = $context['name'];
    }

    return $namesById;
}

function parse_log_entry_id(string $id): int
{
    $id = trim($id);

    if ($id === '' || !ctype_digit($id) || (int) $id < 1) {
        fail("Invalid Log Entry ID: {$id}");
    }

    return (int) $id;
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
        '  php bin/app.php log:add "<title>" [--content "<content>"] [--context <name>|--no-context]',
        '  php bin/app.php log:end <id>',
        '  php bin/app.php log:list',
        '  php bin/app.php log:today',
        '  php bin/app.php context:add <name>',
        '  php bin/app.php context:list',
        '  php bin/app.php context:switch <name>',
        '  php bin/app.php context:clear',
    ]);
}

function fail(string $message): void
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}
