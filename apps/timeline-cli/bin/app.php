<?php

declare(strict_types=1);

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoloadPath)) {
    require $autoloadPath;
} else {
    require_context_oop_files();
}

configure_timezone_from_environment();

$command = $argv[1] ?? null;

if ($command === null) {
    fail("Missing command.\n\n" . usage());
}

switch ($command) {
    case 'log:add':
        run_log_entry_command('add', array_slice($argv, 2));
        break;

    case 'log:end':
        run_log_entry_command('end', array_slice($argv, 2));
        break;

    case 'log:edit':
        run_log_entry_command('edit', array_slice($argv, 2));
        break;

    case 'log:list':
        list_log_entries(array_slice($argv, 2), load_log_entries(), load_contexts());
        break;

    case 'log:today':
        list_todays_log_entries(array_slice($argv, 2), load_log_entries(), load_contexts());
        break;

    case 'log:export-csv':
        export_log_entries_csv(array_slice($argv, 2), load_log_entries(), load_contexts());
        break;

    case 'context:add':
        run_context_command('add', array_slice($argv, 2));
        break;

    case 'context:list':
        run_context_command('list', array_slice($argv, 2));
        break;

    case 'context:switch':
        run_context_command('switch', array_slice($argv, 2));
        break;

    case 'context:clear':
        run_context_command('clear', array_slice($argv, 2));
        break;

    default:
        fail("Unknown command: {$command}\n\n" . usage());
}

function add_log_entry(array $args): void
{
    $parsed = parse_arguments(
        'log:add',
        $args,
        ['--content', '--context', '--recorded-at'],
        ['--no-context']
    );
    require_exactly_one_positional('log:add', $parsed['positionals'], 'title');
    require_not_mutually_exclusive($parsed['options'], '--context', '--no-context');

    $title = normalize_title($parsed['positionals'][0], 'Missing required title.');
    $content = array_key_exists('--content', $parsed['options'])
        ? normalize_content_input($parsed['options']['--content'])
        : null;
    $contextName = array_key_exists('--context', $parsed['options'])
        ? normalize_context_name($parsed['options']['--context'])
        : null;
    $useNoContext = array_key_exists('--no-context', $parsed['options']);
    $recordedAt = array_key_exists('--recorded-at', $parsed['options'])
        ? parse_explicit_datetime($parsed['options']['--recorded-at'], '--recorded-at')
        : date('c');

    $entries = load_log_entries();
    $contexts = load_contexts();
    $contextId = resolve_context_id_for_new_log_entry($contexts, $contextName, $useNoContext);
    $entry = [
        'id' => next_log_entry_id($entries),
        'title' => $title,
        'content' => $content,
        'recorded_at' => $recordedAt,
        'ended_at' => null,
        'context_id' => $contextId,
    ];

    validate_log_entry_state($entry, "Log Entry #{$entry['id']}");

    $entries[] = $entry;
    save_log_entries($entries);

    echo "Added Log Entry #{$entry['id']}: {$entry['title']}\n";
}

function run_context_command(string $method, array $args): void
{
    try {
        context_console()->{$method}($args);
    } catch (TimelineCli\Console\ContextCommandFailed $exception) {
        fail($exception->getMessage());
    }
}

function run_log_entry_command(string $method, array $args): void
{
    try {
        log_entry_console()->{$method}($args);
    } catch (TimelineCli\Console\LogEntryCommandFailed $exception) {
        fail($exception->getMessage());
    }
}

function context_console(): TimelineCli\Console\ContextConsole
{
    $contextRepository = new TimelineCli\Infrastructure\JsonContextRepository(contexts_path());
    $currentContextStore = new TimelineCli\Infrastructure\JsonCurrentContextStore(current_context_path());
    $contextService = new TimelineCli\Application\ContextService($contextRepository, $currentContextStore);

    return new TimelineCli\Console\ContextConsole($contextService);
}

function log_entry_console(): TimelineCli\Console\LogEntryConsole
{
    $logEntryRepository = new TimelineCli\Infrastructure\JsonLogEntryRepository(log_entries_path());
    $contextRepository = new TimelineCli\Infrastructure\JsonContextRepository(contexts_path());
    $currentContextStore = new TimelineCli\Infrastructure\JsonCurrentContextStore(current_context_path());
    $logEntryService = new TimelineCli\Application\LogEntryService(
        $logEntryRepository,
        $contextRepository,
        $currentContextStore
    );

    return new TimelineCli\Console\LogEntryConsole($logEntryService);
}

function end_log_entry(array $args): void
{
    $parsed = parse_arguments('log:end', $args, ['--ended-at'], []);
    require_exactly_one_positional('log:end', $parsed['positionals'], 'Log Entry ID');

    $id = parse_log_entry_id($parsed['positionals'][0]);
    $entries = load_log_entries();
    $entryIndex = find_log_entry_index_by_id($entries, $id);

    if ($entryIndex === null) {
        fail("Log Entry #{$id} was not found.");
    }

    if ($entries[$entryIndex]['ended_at'] !== null) {
        fail("Log Entry #{$id} already has an End Time.");
    }

    $endedAt = array_key_exists('--ended-at', $parsed['options'])
        ? parse_explicit_datetime($parsed['options']['--ended-at'], '--ended-at')
        : date('c');

    $updatedEntry = $entries[$entryIndex];
    $updatedEntry['ended_at'] = $endedAt;
    validate_log_entry_state($updatedEntry, "Log Entry #{$id}");

    $entries[$entryIndex] = $updatedEntry;
    save_log_entries($entries);

    echo "Ended Log Entry #{$id}: {$entries[$entryIndex]['ended_at']}\n";
}

function edit_log_entry(array $args): void
{
    $parsed = parse_arguments(
        'log:edit',
        $args,
        ['--title', '--content', '--recorded-at', '--ended-at', '--context'],
        ['--clear-content', '--clear-ended-at', '--no-context']
    );
    require_exactly_one_positional('log:edit', $parsed['positionals'], 'Log Entry ID');

    if (count($parsed['options']) === 0) {
        fail('Command log:edit requires at least one change option.');
    }

    require_not_mutually_exclusive($parsed['options'], '--content', '--clear-content');
    require_not_mutually_exclusive($parsed['options'], '--ended-at', '--clear-ended-at');
    require_not_mutually_exclusive($parsed['options'], '--context', '--no-context');

    $id = parse_log_entry_id($parsed['positionals'][0]);
    $entries = load_log_entries();
    $contexts = load_contexts();
    $entryIndex = find_log_entry_index_by_id($entries, $id);

    if ($entryIndex === null) {
        fail("Log Entry #{$id} was not found.");
    }

    $originalEntry = $entries[$entryIndex];
    $updatedEntry = $originalEntry;

    if (array_key_exists('--title', $parsed['options'])) {
        $updatedEntry['title'] = normalize_title($parsed['options']['--title'], 'Title cannot be empty.');
    }

    if (array_key_exists('--content', $parsed['options'])) {
        $updatedEntry['content'] = normalize_content_input($parsed['options']['--content']);
    }

    if (array_key_exists('--clear-content', $parsed['options'])) {
        $updatedEntry['content'] = null;
    }

    if (array_key_exists('--recorded-at', $parsed['options'])) {
        $updatedEntry['recorded_at'] = parse_explicit_datetime($parsed['options']['--recorded-at'], '--recorded-at');
    }

    if (array_key_exists('--ended-at', $parsed['options'])) {
        $updatedEntry['ended_at'] = parse_explicit_datetime($parsed['options']['--ended-at'], '--ended-at');
    }

    if (array_key_exists('--clear-ended-at', $parsed['options'])) {
        $updatedEntry['ended_at'] = null;
    }

    if (array_key_exists('--context', $parsed['options'])) {
        $contextName = normalize_context_name($parsed['options']['--context']);
        $context = find_context_by_name($contexts, $contextName);

        if ($context === null) {
            fail("Context not found: {$contextName}");
        }

        $updatedEntry['context_id'] = $context['id'];
    }

    if (array_key_exists('--no-context', $parsed['options'])) {
        $updatedEntry['context_id'] = null;
    }

    validate_log_entry_state($updatedEntry, "Log Entry #{$id}");

    if ($updatedEntry === $originalEntry) {
        fail("Command log:edit did not change Log Entry #{$id}.");
    }

    $entries[$entryIndex] = $updatedEntry;
    save_log_entries($entries);

    echo "Updated Log Entry #{$id}.\n";
}

function list_log_entries(array $args, array $entries, array $contexts): void
{
    $query = parse_log_entry_query_options('log:list', $args, $contexts, true, true);
    $filteredEntries = filter_log_entries($entries, $query);

    print_log_entries(sort_log_entries_by_recorded_time($filteredEntries, $query['order']), $contexts);
}

function list_todays_log_entries(array $args, array $entries, array $contexts): void
{
    $query = parse_log_entry_query_options('log:today', $args, $contexts, false, true);
    $query['date'] = date('Y-m-d');
    $filteredEntries = filter_log_entries($entries, $query);

    print_log_entries(sort_log_entries_by_recorded_time($filteredEntries, $query['order']), $contexts);
}

function export_log_entries_csv(array $args, array $entries, array $contexts): void
{
    $parsed = parse_arguments(
        'log:export-csv',
        $args,
        ['--date', '--from', '--to', '--context', '--output'],
        ['--no-context']
    );
    require_no_positionals('log:export-csv', $parsed['positionals']);

    $query = build_log_entry_query('log:export-csv', $parsed['options'], $contexts, true, false);
    $filteredEntries = sort_log_entries_by_recorded_time(filter_log_entries($entries, $query), 'asc');
    $csv = render_log_entries_csv($filteredEntries, $contexts);

    if (array_key_exists('--output', $parsed['options'])) {
        $path = trim($parsed['options']['--output']);

        if ($path === '') {
            fail('Output path cannot be empty.');
        }

        $directory = dirname($path);
        if ($directory !== '.' && !is_dir($directory)) {
            fail("Output directory does not exist: {$directory}");
        }

        if (file_put_contents($path, $csv, LOCK_EX) === false) {
            fail("Could not write CSV output file: {$path}");
        }

        return;
    }

    echo $csv;
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

        if ($entry['ended_at'] !== null) {
            $line .= " -> {$entry['ended_at']}";
        }

        if ($entry['context_id'] !== null) {
            $contextName = $contextNamesById[$entry['context_id']] ?? "Context #{$entry['context_id']}";
            $line .= " [{$contextName}]";
        }

        $line .= " {$entry['title']}";
        echo $line . "\n";
    }
}

function parse_arguments(string $command, array $args, array $valueOptions, array $flagOptions): array
{
    $positionals = [];
    $options = [];
    $valueOptionsByName = array_fill_keys($valueOptions, true);
    $flagOptionsByName = array_fill_keys($flagOptions, true);

    for ($index = 0; $index < count($args); $index++) {
        $arg = $args[$index];

        if (!is_string($arg)) {
            fail("Invalid argument for {$command}.");
        }

        if (!str_starts_with($arg, '--')) {
            $positionals[] = $arg;
            continue;
        }

        if (!isset($valueOptionsByName[$arg]) && !isset($flagOptionsByName[$arg])) {
            fail("Unknown option for {$command}: {$arg}");
        }

        if (array_key_exists($arg, $options)) {
            fail("Duplicate option for {$command}: {$arg}");
        }

        if (isset($flagOptionsByName[$arg])) {
            $options[$arg] = true;
            continue;
        }

        if (!array_key_exists($index + 1, $args) || str_starts_with((string) $args[$index + 1], '--')) {
            fail("Missing value for {$arg}.");
        }

        $options[$arg] = $args[$index + 1];
        $index++;
    }

    return [
        'positionals' => $positionals,
        'options' => $options,
    ];
}

function require_exactly_one_positional(string $command, array $positionals, string $name): void
{
    if (count($positionals) === 0 || trim((string) $positionals[0]) === '') {
        fail("Missing required {$name}.\n\n" . command_usage($command));
    }

    if (count($positionals) > 1) {
        fail("Command {$command} accepts exactly one {$name}.");
    }
}

function require_no_positionals(string $command, array $positionals): void
{
    if (count($positionals) > 0) {
        fail("Command {$command} does not accept positional arguments.");
    }
}

function require_not_mutually_exclusive(array $options, string $first, string $second): void
{
    if (array_key_exists($first, $options) && array_key_exists($second, $options)) {
        fail("Cannot provide both {$first} and {$second}.");
    }
}

function parse_log_entry_query_options(
    string $command,
    array $args,
    array $contexts,
    bool $allowDateFilters,
    bool $allowOrder
): array {
    $valueOptions = ['--context'];

    if ($allowDateFilters) {
        $valueOptions[] = '--date';
        $valueOptions[] = '--from';
        $valueOptions[] = '--to';
    }

    if ($allowOrder) {
        $valueOptions[] = '--order';
    }

    $parsed = parse_arguments($command, $args, $valueOptions, ['--no-context']);
    require_no_positionals($command, $parsed['positionals']);

    return build_log_entry_query($command, $parsed['options'], $contexts, $allowDateFilters, $allowOrder);
}

function build_log_entry_query(
    string $command,
    array $options,
    array $contexts,
    bool $allowDateFilters,
    bool $allowOrder
): array {
    if (!$allowDateFilters) {
        foreach (['--date', '--from', '--to'] as $option) {
            if (array_key_exists($option, $options)) {
                fail("Unknown option for {$command}: {$option}");
            }
        }
    }

    require_not_mutually_exclusive($options, '--date', '--from');
    require_not_mutually_exclusive($options, '--date', '--to');
    require_not_mutually_exclusive($options, '--context', '--no-context');

    $date = array_key_exists('--date', $options) ? parse_local_date($options['--date'], '--date') : null;
    $from = array_key_exists('--from', $options) ? parse_local_date($options['--from'], '--from') : null;
    $to = array_key_exists('--to', $options) ? parse_local_date($options['--to'], '--to') : null;

    if ($from !== null && $to !== null && $from > $to) {
        fail('--from must be on or before --to.');
    }

    $contextId = null;
    $filterNoContext = array_key_exists('--no-context', $options);

    if (array_key_exists('--context', $options)) {
        $contextName = normalize_context_name($options['--context']);
        $context = find_context_by_name($contexts, $contextName);

        if ($context === null) {
            fail("Context not found: {$contextName}");
        }

        $contextId = $context['id'];
    }

    $order = 'desc';
    if ($allowOrder && array_key_exists('--order', $options)) {
        $order = strtolower(trim($options['--order']));

        if ($order !== 'asc' && $order !== 'desc') {
            fail("Invalid order: {$options['--order']}. Use asc or desc.");
        }
    }

    return [
        'date' => $date,
        'from' => $from,
        'to' => $to,
        'context_id' => $contextId,
        'no_context' => $filterNoContext,
        'order' => $order,
    ];
}

function parse_local_date(string $value, string $option): string
{
    $value = trim($value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    if (!$date instanceof DateTimeImmutable || !date_parse_succeeded() || $date->format('Y-m-d') !== $value) {
        fail("Invalid date for {$option}: {$value}. Use Y-m-d.");
    }

    return $value;
}

function filter_log_entries(array $entries, array $query): array
{
    return array_values(array_filter($entries, function (array $entry) use ($query): bool {
        $recordedDate = local_recorded_date($entry);

        if ($query['date'] !== null && $recordedDate !== $query['date']) {
            return false;
        }

        if ($query['from'] !== null && $recordedDate < $query['from']) {
            return false;
        }

        if ($query['to'] !== null && $recordedDate > $query['to']) {
            return false;
        }

        if ($query['context_id'] !== null && ($entry['context_id'] ?? null) !== $query['context_id']) {
            return false;
        }

        if ($query['no_context'] && ($entry['context_id'] ?? null) !== null) {
            return false;
        }

        return true;
    }));
}

function local_recorded_date(array $entry): string
{
    return parse_stored_datetime((string) ($entry['recorded_at'] ?? ''), 'recorded_at')
        ->setTimezone(new DateTimeZone(date_default_timezone_get()))
        ->format('Y-m-d');
}

function render_log_entries_csv(array $entries, array $contexts): string
{
    $stream = fopen('php://temp', 'r+');
    if ($stream === false) {
        fail('Could not open temporary stream for CSV output.');
    }

    $contextNamesById = context_names_by_id($contexts);
    fputcsv($stream, ['id', 'title', 'content', 'recorded_at', 'ended_at', 'context_id', 'context_name'], ',', '"', '');

    foreach ($entries as $entry) {
        $contextId = $entry['context_id'];
        fputcsv($stream, [
            $entry['id'],
            $entry['title'],
            $entry['content'] ?? '',
            $entry['recorded_at'],
            $entry['ended_at'] ?? '',
            $contextId ?? '',
            $contextId === null ? '' : ($contextNamesById[$contextId] ?? "Context #{$contextId}"),
        ], ',', '"', '');
    }

    rewind($stream);
    $csv = stream_get_contents($stream);
    fclose($stream);

    if ($csv === false) {
        fail('Could not read CSV output.');
    }

    return $csv;
}

function normalize_title(string $title, string $emptyMessage): string
{
    $title = trim($title);

    if ($title === '') {
        fail($emptyMessage);
    }

    return $title;
}

function normalize_content_input(string $content): string
{
    if (trim($content) === '') {
        return '';
    }

    return $content;
}

function normalize_context_name(string $name): string
{
    $name = trim($name);

    if ($name === '') {
        fail('Context name cannot be empty.');
    }

    return $name;
}

function parse_explicit_datetime(string $value, string $option): string
{
    $value = trim($value);

    if ($value === '') {
        fail("Invalid date/time for {$option}: value cannot be empty.");
    }

    $timezone = new DateTimeZone(date_default_timezone_get());
    $localDateTime = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $value, $timezone);

    if ($localDateTime instanceof DateTimeImmutable && date_parse_succeeded() && $localDateTime->format('Y-m-d H:i') === $value) {
        return $localDateTime->format(DateTimeInterface::ATOM);
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value) === 1) {
        $utc = new DateTimeZone('UTC');
        $isoDateTime = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, $utc);

        if ($isoDateTime instanceof DateTimeImmutable && date_parse_succeeded() && $isoDateTime->format('Y-m-d\TH:i:s\Z') === $value) {
            return $isoDateTime->format(DateTimeInterface::ATOM);
        }
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $value) === 1) {
        $isoDateTime = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $value);

        if ($isoDateTime instanceof DateTimeImmutable && date_parse_succeeded() && $isoDateTime->format(DateTimeInterface::ATOM) === $value) {
            return $isoDateTime->format(DateTimeInterface::ATOM);
        }
    }

    fail("Invalid date/time for {$option}: {$value}. Use Y-m-d H:i or ISO 8601.");
}

function parse_stored_datetime(string $value, string $fieldName): DateTimeImmutable
{
    $dateTime = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);

    if (!$dateTime instanceof DateTimeImmutable || !date_parse_succeeded() || $dateTime->format(DateTimeInterface::ATOM) !== $value) {
        fail("Stored {$fieldName} must be ISO 8601: {$value}");
    }

    return $dateTime;
}

function date_parse_succeeded(): bool
{
    $errors = DateTimeImmutable::getLastErrors();

    return $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
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

    validate_log_entries($entries, $path);

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

    validate_contexts($contexts, $path);

    return $contexts;
}

function save_contexts(array $contexts): void
{
    validate_contexts($contexts, 'Contexts being saved');

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

    if (!is_array($currentContext) || is_list_array($currentContext) || array_keys($currentContext) !== ['current_context_id']) {
        fail("Storage file must contain a Current Context object: {$path}");
    }

    $currentContextId = $currentContext['current_context_id'];
    if ($currentContextId !== null && (!is_int($currentContextId) || $currentContextId < 1)) {
        fail("Current Context ID must be a positive integer or null: {$path}");
    }

    return $currentContextId;
}

function save_current_context_id(?int $contextId): void
{
    if ($contextId !== null && $contextId < 1) {
        fail('Current Context ID must be a positive integer or null.');
    }

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
    validate_log_entries($entries, 'Log Entries being saved');

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

function validate_log_entries(array $entries, string $source): void
{
    $seenIds = [];

    foreach ($entries as $index => $entry) {
        if (!is_array($entry) || is_list_array($entry)) {
            fail("Invalid Log Entry record at index {$index} in {$source}: expected object.");
        }

        validate_log_entry_state($entry, "Log Entry record at index {$index} in {$source}");

        if (isset($seenIds[$entry['id']])) {
            fail("Duplicate Log Entry ID in {$source}: #{$entry['id']}");
        }

        $seenIds[$entry['id']] = true;
    }
}

function validate_log_entry_state(array $entry, string $source): void
{
    $expectedKeys = ['id', 'title', 'content', 'recorded_at', 'ended_at', 'context_id'];
    $actualKeys = array_keys($entry);
    sort($actualKeys);
    $sortedExpectedKeys = $expectedKeys;
    sort($sortedExpectedKeys);

    if ($actualKeys !== $sortedExpectedKeys) {
        fail("Invalid {$source}: expected fields id, title, content, recorded_at, ended_at, context_id.");
    }

    if (!is_int($entry['id']) || $entry['id'] < 1) {
        fail("Invalid {$source}: id must be a positive integer.");
    }

    if (!is_string($entry['title']) || trim($entry['title']) === '') {
        fail("Invalid {$source}: title must be a non-empty string.");
    }

    if ($entry['content'] !== null && !is_string($entry['content'])) {
        fail("Invalid {$source}: content must be a string or null.");
    }

    if (!is_string($entry['recorded_at'])) {
        fail("Invalid {$source}: recorded_at must be an ISO 8601 string.");
    }

    $recordedAt = parse_stored_datetime($entry['recorded_at'], 'recorded_at');

    if ($entry['ended_at'] !== null && !is_string($entry['ended_at'])) {
        fail("Invalid {$source}: ended_at must be an ISO 8601 string or null.");
    }

    if ($entry['ended_at'] !== null) {
        $endedAt = parse_stored_datetime($entry['ended_at'], 'ended_at');

        if ($endedAt < $recordedAt) {
            fail("Invalid {$source}: ended_at cannot be earlier than recorded_at.");
        }
    }

    if ($entry['context_id'] !== null && (!is_int($entry['context_id']) || $entry['context_id'] < 1)) {
        fail("Invalid {$source}: context_id must be a positive integer or null.");
    }
}

function validate_contexts(array $contexts, string $source): void
{
    $seenIds = [];
    $seenNames = [];

    foreach ($contexts as $index => $context) {
        if (!is_array($context) || is_list_array($context)) {
            fail("Invalid Context record at index {$index} in {$source}: expected object.");
        }

        validate_context_state($context, "Context record at index {$index} in {$source}");

        if (isset($seenIds[$context['id']])) {
            fail("Duplicate Context ID in {$source}: #{$context['id']}");
        }

        $nameKey = context_name_key($context['name']);
        if (isset($seenNames[$nameKey])) {
            fail("Duplicate Context name in {$source}: {$context['name']}");
        }

        $seenIds[$context['id']] = true;
        $seenNames[$nameKey] = true;
    }
}

function validate_context_state(array $context, string $source): void
{
    $expectedKeys = ['id', 'name'];
    $actualKeys = array_keys($context);
    sort($actualKeys);
    sort($expectedKeys);

    if ($actualKeys !== $expectedKeys) {
        fail("Invalid {$source}: expected fields id, name.");
    }

    if (!is_int($context['id']) || $context['id'] < 1) {
        fail("Invalid {$source}: id must be a positive integer.");
    }

    if (!is_string($context['name']) || trim($context['name']) === '') {
        fail("Invalid {$source}: name must be a non-empty string.");
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

function find_log_entry_index_by_id(array $entries, int $id): ?int
{
    foreach ($entries as $index => $entry) {
        if (($entry['id'] ?? null) === $id) {
            return $index;
        }
    }

    return null;
}

function find_context_by_name(array $contexts, string $name): ?array
{
    $nameKey = context_name_key($name);

    foreach ($contexts as $context) {
        if (isset($context['name']) && is_string($context['name']) && context_name_key($context['name']) === $nameKey) {
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

function context_name_key(string $name): string
{
    return strtolower($name);
}

function parse_log_entry_id(string $id): int
{
    $id = trim($id);

    if ($id === '' || !ctype_digit($id) || (int) $id < 1) {
        fail("Invalid Log Entry ID: {$id}");
    }

    return (int) $id;
}

function sort_log_entries_by_recorded_time(array $entries, string $order): array
{
    usort($entries, function (array $a, array $b) use ($order): int {
        $recordedAtA = parse_stored_datetime((string) ($a['recorded_at'] ?? ''), 'recorded_at');
        $recordedAtB = parse_stored_datetime((string) ($b['recorded_at'] ?? ''), 'recorded_at');
        $comparison = $recordedAtA <=> $recordedAtB;

        if ($comparison === 0) {
            $comparison = ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
        }

        return $order === 'desc' ? -$comparison : $comparison;
    });

    return $entries;
}

function require_no_arguments(string $command, array $args): void
{
    foreach ($args as $arg) {
        if (is_string($arg) && str_starts_with($arg, '--')) {
            fail("Unknown option for {$command}: {$arg}");
        }
    }

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

function require_context_oop_files(): void
{
    $src = dirname(__DIR__) . '/src';

    foreach ([
        '/Domain/Exception/InvalidContext.php',
        '/Domain/Exception/InvalidLogEntry.php',
        '/Domain/Context.php',
        '/Domain/LogEntry.php',
        '/Domain/ContextRepository.php',
        '/Domain/LogEntryRepository.php',
        '/Domain/CurrentContextStore.php',
        '/Application/Exception/ContextNotFound.php',
        '/Application/Exception/DuplicateContextName.php',
        '/Application/Exception/LogEntryNotFound.php',
        '/Application/ContextList.php',
        '/Application/LogEntryChanges.php',
        '/Application/ContextService.php',
        '/Application/LogEntryService.php',
        '/Infrastructure/StorageFailure.php',
        '/Infrastructure/JsonContextRepository.php',
        '/Infrastructure/JsonLogEntryRepository.php',
        '/Infrastructure/JsonCurrentContextStore.php',
        '/Console/ContextCommandFailed.php',
        '/Console/LogEntryCommandFailed.php',
        '/Console/ContextConsole.php',
        '/Console/LogEntryConsole.php',
    ] as $file) {
        require_once $src . $file;
    }
}

function usage(): string
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

function command_usage(string $command): string
{
    $lines = [
        'log:add' => 'Usage: php bin/app.php log:add "<title>" [--content "<content>"] [--recorded-at "<datetime>"] [--context <name>|--no-context]',
        'log:end' => 'Usage: php bin/app.php log:end <id> [--ended-at "<datetime>"]',
        'log:edit' => 'Usage: php bin/app.php log:edit <id> [--title "<title>"] [--content "<content>"|--clear-content] [--recorded-at "<datetime>"] [--ended-at "<datetime>"|--clear-ended-at] [--context <name>|--no-context]',
        'log:list' => 'Usage: php bin/app.php log:list [--date <Y-m-d>|--from <Y-m-d> [--to <Y-m-d>]|--to <Y-m-d>] [--context <name>|--no-context] [--order asc|desc]',
        'log:today' => 'Usage: php bin/app.php log:today [--context <name>|--no-context] [--order asc|desc]',
        'log:export-csv' => 'Usage: php bin/app.php log:export-csv [--date <Y-m-d>|--from <Y-m-d> [--to <Y-m-d>]|--to <Y-m-d>] [--context <name>|--no-context] [--output <path>]',
        'context:add' => 'Usage: php bin/app.php context:add <name>',
        'context:switch' => 'Usage: php bin/app.php context:switch <name>',
    ];

    return $lines[$command] ?? usage();
}

function fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}
