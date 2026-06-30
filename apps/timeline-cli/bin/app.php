<?php
declare(strict_types=1);

use TimelineCli\Application\ContextService;
use TimelineCli\Application\LogEntryService;
use TimelineCli\Console\ConsoleApplication;
use TimelineCli\Console\ContextConsole;
use TimelineCli\Console\LogEntryConsole;
use TimelineCli\Infrastructure\JsonContextRepository;
use TimelineCli\Infrastructure\JsonCurrentContextStore;
use TimelineCli\Infrastructure\JsonLogEntryRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

configure_timezone_from_environment();

$dataDir = dirname(__DIR__) . '/data';
$contextRepository = new JsonContextRepository($dataDir . '/contexts.json');
$currentContextStore = new JsonCurrentContextStore($dataDir . '/current_context.json');
$logEntryRepository = new JsonLogEntryRepository($dataDir . '/log_entries.json');

$application = new ConsoleApplication(
    new LogEntryConsole(new LogEntryService($logEntryRepository, $contextRepository, $currentContextStore)),
    new ContextConsole(new ContextService($contextRepository, $currentContextStore))
);

exit($application->run($argv));

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
