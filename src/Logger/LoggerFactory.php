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
     * @param string $path   File path or stream for log output.
     */
    public static function build(
        string $name = 'app',
        string $path = 'php://stdout',
        Level $level = Level::Debug
    ): LoggerInterface {
        $logger = new Logger($name);
        $logger->pushHandler(new StreamHandler($path, $level));
        return $logger;
    }
}