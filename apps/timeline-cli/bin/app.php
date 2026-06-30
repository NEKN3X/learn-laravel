<?php
declare(strict_types=1);

use TimelineCli\Infrastructure\RuntimeBootstrap;

require dirname(__DIR__) . '/vendor/autoload.php';

exit((new RuntimeBootstrap(dirname(__DIR__)))->run($argv));
