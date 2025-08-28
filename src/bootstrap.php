<?php

use App\Container\ContainerFactory;
use App\Logger\LoggerFactory;
use Dotenv\Dotenv;
use Monolog\Level;
use Psr\Log\LoggerInterface;

$startTime = microtime(true);
//echo "Loading bootstrap...\n";

// CONFIGS
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
date_default_timezone_set('UTC');

// PHP FILES
require_once __DIR__ . '/common.php';

// BINDING SINGLETONS
$container = ContainerFactory::build();
$logger = LoggerFactory::build('wallet-nile-cron', Level::Info);
$container->set(LoggerInterface::class, $logger);
$container->set('logger', $logger);
$app_dir = __DIR__ . '/../';

//echo "Bootstrap loaded in " . (microtime(true) - $startTime) . " seconds.\n";