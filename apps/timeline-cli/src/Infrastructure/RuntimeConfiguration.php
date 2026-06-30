<?php

declare(strict_types=1);

namespace TimelineCli\Infrastructure;

use Dotenv\Dotenv;

final class RuntimeConfiguration
{
    private function __construct(
        private string $dataDir,
        private string $logFile
    ) {
    }

    public static function load(string $appRoot): self
    {
        Dotenv::createImmutable($appRoot)->safeLoad();

        return self::fromEnvironment($appRoot);
    }

    public static function defaults(string $appRoot): self
    {
        return new self(
            self::resolvePath($appRoot, 'data'),
            self::resolvePath($appRoot, 'logs/app.log')
        );
    }

    public static function configureTimezoneFromEnvironment(): void
    {
        $timezone = getenv('TZ');

        if (!is_string($timezone) || trim($timezone) === '') {
            return;
        }

        if (in_array($timezone, timezone_identifiers_list(), true)) {
            date_default_timezone_set($timezone);
        }
    }

    public function dataDir(): string
    {
        return $this->dataDir;
    }

    public function logFile(): string
    {
        return $this->logFile;
    }

    private static function fromEnvironment(string $appRoot): self
    {
        return new self(
            self::resolvePath($appRoot, self::env('TIMELINE_DATA_DIR') ?? 'data'),
            self::resolvePath($appRoot, self::env('TIMELINE_LOG_FILE') ?? 'logs/app.log')
        );
    }

    private static function env(string $name): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }

    private static function resolvePath(string $appRoot, string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return $path;
        }

        return rtrim($appRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
    }
}
