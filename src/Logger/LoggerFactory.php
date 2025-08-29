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

        if (env('APP_DEBUG')) {
            $logger->pushHandler(new StreamHandler('php://stdout', $level));

            if (php_sapi_name() !== 'cli' && isset($_SERVER['HTTP_USER_AGENT'])) {
                // echo errors to browser for easier debugging
                header('Content-Type: application/json');
                $logger->pushHandler(new StreamHandler('php://output', $level));
            }
        }
        return $logger;
    }
}