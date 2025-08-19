<?php
declare(strict_types=1);

namespace App\Logger;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final class LoggerFactory
{
    /**
     * Create a new logger instance.
     *
     * @param string $name   Logger channel name.
     */
    public static function build(
        string $name = LoggerNameEnum::DEFAULT_LOGGER_NAME,
        Level $level = Level::Debug
    ): LoggerInterface {
        $path = getAppRoot() . '/storage/logs/application.log';
        $logger = new Logger($name);
        $logger->pushHandler(new StreamHandler($path, $level));
        return $logger;
    }
}