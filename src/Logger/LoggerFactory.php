<?php
declare(strict_types=1);

namespace App\Logger;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final class LoggerFactory
{
    /**
     * Create a new logger instance.
     *
     * @param string $name   Logger channel name.
     * @param string $path   File path or stream for log output.
     * @param string|int $level Log level (Monolog::DEBUG, Monolog::INFO, etc.).
     */
    public static function build(
        string $name = 'app',
        string $path = 'php://stdout',
        string|int $level = Logger::DEBUG
    ): LoggerInterface {
        $logger = new Logger($name);
        $logger->pushHandler(new StreamHandler($path, $level));
        return $logger;
    }
}