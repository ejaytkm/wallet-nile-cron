<?php
declare(strict_types=1);

use Predis\Response\Status;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/bootstrap.php';

global $container;

$merchantRp_1 = new App\Repositories\MerchantRepo('WALLET_0');
$merchantRp_2 = new App\Repositories\MerchantRepo('WALLET_1');
$globalRp = new App\Repositories\JobRepo();
$redis = new App\Utils\RedisUtil();

/** @var Status $status */
$rStatus = $redis->getClient()->ping();

try {
    $connection_1 = $merchantRp_1->testConnection();
    $connection_2 = $merchantRp_2->testConnection();
    $connection_global = $globalRp->testConnection();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "FAIL",
        "error" => $e->getMessage(),
    ]);
    exit;
}

$data = [
    "status" => "OK",
    "merchant1" => $connection_1,
    "merchant2" => $connection_2,
    "global" => $connection_global,
    "redis" => $rStatus->getPayload() === 'PONG' ? 'OK' : 'FAIL',
];

header('Content-Type: application/json');
echo json_encode($data);
exit;