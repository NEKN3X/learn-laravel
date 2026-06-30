<?php

declare(strict_types=1);

namespace TimelineCli\Infrastructure;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final class RuntimeLogger
{
    public static function create(string $logFile): LoggerInterface
    {
        $logger = new Logger('timeline-cli');
        $logger->pushHandler(new StreamHandler($logFile, Logger::ERROR));

        return $logger;
    }
}
