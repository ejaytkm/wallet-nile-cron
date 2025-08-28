<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/bootstrap.php';

global $container;

$merchantRp_1 = new App\Repositories\MerchantRepo('WALLET_0');
$merchantRp_2 = new App\Repositories\MerchantRepo('WALLET_1');
$globalRp = new App\Repositories\JobRepo();
$redis = new App\Utils\RedisUtil();

/** @var \Predis\Response\Status $status */
$rStatus = $redis->getClient()->ping();

$data = [
    "status" => "OK",
    "merchant_1" => $merchantRp_1->testConnection(),
    "merchant_2" => $merchantRp_2->testConnection(),
    "global" => $globalRp->testConnection(),
    "redis" => $rStatus->getPayload() === 'PONG' ? 'OK' : 'FAIL',
];

header('Content-Type: application/json');
echo json_encode($data);
exit;