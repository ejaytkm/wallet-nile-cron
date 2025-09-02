<?php
declare(strict_types=1);

use App\Repositories\MerchantRepo;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/bootstrap.php';

$minute = Carbon\Carbon::now()->minute;
$apiDir = getMerchantServerConfig(0,'APIDIR');
$cronDir = getMerchantServerConfig(0,'CRONDIR');
$self = "http://" . env('APP_URL');

global $container;
$logger = $container->get(Psr\Log\LoggerInterface::class);

try {
    // SYNC_BET_HISTORY
   $envs =  MerchantRepo::WALLET_ENVS;
    foreach ($envs as $e) {
        postAndForget($self . '/crond/syncBetHistory.php?wEnv=' . $e);
    }
    $now = Carbon\Carbon::now()->floorMinute()->format('H:i:s');
    $logger->info("Called at $now syncBetHistory.php - " . json_encode($envs));
} catch (Throwable $e) {
    $logger->error("Error at cron.php" . $e->getMessage(), [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
