<?php

declare(strict_types=1);

namespace TimelineCli\Infrastructure;

use Psr\Log\LoggerInterface;
use Throwable;
use TimelineCli\Application\ContextService;
use TimelineCli\Application\LogEntryService;
use TimelineCli\Console\ConsoleApplication;
use TimelineCli\Console\ContextConsole;
use TimelineCli\Console\LogEntryConsole;

final class RuntimeBootstrap
{
    public function __construct(private string $appRoot)
    {
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $configuration = RuntimeConfiguration::defaults($this->appRoot);
        $logger = RuntimeLogger::create($configuration->logFile());

        try {
            $configuration = RuntimeConfiguration::load($this->appRoot);
            RuntimeConfiguration::configureTimezoneFromEnvironment();
            $logger = RuntimeLogger::create($configuration->logFile());

            return $this->buildApplication($configuration, $logger)->run($argv);
        } catch (Throwable $exception) {
            $this->logFailure($logger, 'Timeline CLI bootstrap failed.', $exception);
            fwrite(STDERR, $exception->getMessage() . "\n");

            return 1;
        }
    }

    private function buildApplication(RuntimeConfiguration $configuration, LoggerInterface $logger): ConsoleApplication
    {
        $dataDir = $configuration->dataDir();
        $contextRepository = new JsonContextRepository($dataDir . '/contexts.json');
        $currentContextStore = new JsonCurrentContextStore($dataDir . '/current_context.json');
        $logEntryRepository = new JsonLogEntryRepository($dataDir . '/log_entries.json');

        return new ConsoleApplication(
            new LogEntryConsole(new LogEntryService($logEntryRepository, $contextRepository, $currentContextStore)),
            new ContextConsole(new ContextService($contextRepository, $currentContextStore)),
            $logger
        );
    }

    private function logFailure(LoggerInterface $logger, string $message, Throwable $exception): void
    {
        try {
            $logger->error($message, ['exception' => $exception]);
        } catch (Throwable) {
        }
    }
}
