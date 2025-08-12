<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/Bootstrap.php';

use App\Database\PDOAbstract;
use App\Providers\SyncBetActivity;
use App\Utils\GuzzleUtil;
use Carbon\Carbon;

$pdo = new PDO(env('DB_DSN'), env('DB_USER'), env('DB_PASS'));
$db  = new PDOAbstract($pdo);

$pdo_merchant = new PDO(env('DB_MERCHANT_DSN'), env('DB_MERCHANT_USER'), env('DB_MERCHANT_PASS'));
$db_merchant  = new PDOAbstract($pdo_merchant);

// @TODO: make this into an XML
$configs = [
    'JILI' => [
        'interval' => 5, // minutes
        'module' => 'jili',
        'active' => true
    ],
    'JILI2' => [
        'interval' => 5,
        'module' => 'jili',
        'active' => false
    ],
    'JILI3' => [
        'interval' => 5,
        'module' => 'jili',
        'active' => false
    ]
];
$workerUrl  = env('WORKER_LB_URL', 'http://localhost:9501') . '/batch/syncbet';
$activity = new SyncBetActivity($db_merchant);
$startTime = Carbon::now()->format('Y-m-d\TH:i:sP');

foreach ($configs as $site => $config) {
    if (!$config['active']) {
        continue; // Skip inactive sites
    }

    $rows = $activity->fetchActiveMerchantIdsForPwdSite($site, $startTime);
    $mids = array_column($rows, 'merchant_id');

    if (empty($mids)) {
        continue; // No active merchants for this site
    }

    // Prepare the payload for the worker
    $payload = [
        'site'     => $site,
        'module'   => $config['module'],
        'mids'     => $mids,
        'start_time' => $startTime,
        'timeout'  => 15,
        'retry'    => 1,
    ];

    try {
        $resp = (new GuzzleUtil())->execute('POST',
            $workerUrl ,
            ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            $payload
        );
    } catch (Exception $e) {
        echo "Error processing site $site: " . $e->getMessage() . PHP_EOL;
        // TODO: Log errors to storage/logs

        continue; // Skip to the next site on error
    }
}
