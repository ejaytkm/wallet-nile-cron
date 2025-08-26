<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/bootstrap.php';

$minute = Carbon\Carbon::now()->minute;
$apiDir = getMerchantServerConfig(0,'APIDIR');
$cronDir = getMerchantServerConfig(0,'CRONDIR');
$self = "http://" . env('APP_URL');

global $container;
$logger = $container->get(Psr\Log\LoggerInterface::class);

try {
    postAndForget($self . '/crond/syncBetHistory.php');
    $logger->info("Called syncBetHistory.php");
} catch (Throwable $e) {
    $logger->error("Error at cron.php" . $e->getMessage(), [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
