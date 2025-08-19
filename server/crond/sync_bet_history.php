<?php
declare(strict_types=1);

$startTime = microtime(true);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/bootstrap.php';

use Carbon\Carbon;

$merchantRp = new App\Repositories\MerchantRepo;
$wrodb = $merchantRp->getDB();
$redis = new App\Utils\RedisUtil();
$jobs = [
    'JILI'  => [1, 'jili'],
    'JILI2' => [1, 'jili'],
    'JILI3' => [1, 'jili']
];
$jobK = array_keys($jobs);
$currentTimestamp = strtotime('now');
$cron = [];
$query = "SELECT * FROM cron_jobs WHERE  code IN ('" . implode("','", $jobK) . "')";
$cJobs = $wrodb->query($query);
foreach ($cJobs as $c) {
    $cron[$c['merchant_id']][$c['code']] = $c;
}

$mIds = $wrodb->queryFirstColumn("SELECT id FROM merchants WHERE status = 'ACTIVE'");

$batch = [];
foreach ($mIds as $mId) {
    $uniq = [];
    $cacheKey = 'USEDSITE-' . $mId;
    $as = $redis->get($cacheKey);

    if (!is_array($as)) {
        $as = [];
        $sites = $wrodb->queryFirstColumn("SELECT site_name FROM pwd_merchant_site WHERE merchant_id = %i AND status = 'ACTIVE' AND key_1 IS NOT NULL", $mId);
        if (!empty($sites)) {
            $mDT = date('Y-m-d H:i:s', strtotime('-2 hour'));
            $us = $wrodb->queryFirstColumn("SELECT DISTINCT site FROM user_site_transaction_log WHERE merchant_id = %i AND created_datetime > %s", $mId, $mDT);
            foreach ($us as $s) {
                $tmp = explode('-', $s);
                if (in_array($tmp[0], $sites) && !in_array($tmp[0], $as)) {
                    $as[] = $tmp[0];
                }
            }
        }
        if (empty($as)) {
            $as = $sites;
            $redis->set($cacheKey, $as, 300);
        }
    }

    foreach ($as as $site) {
        if (strpos($site, 'MDBO') === 0) {
            $site = 'MDBO';
        } else if (strpos($site, 'GMS') === 0) {
            $site = 'GMS';
        } else if (strpos($site, 'ETG') === 0) {
            $site = 'ETG';
        } else if (strpos($site, 'AVGX') === 0) {
            $site = 'AVGX';
        } else if (strpos($site, 'SMART') === 0) {
            $site = 'SMART';
        } else if (strpos($site, 'ATLAS') === 0) {
            $site = 'ATLAS';
        } else if (strpos($site, 'GPK') === 0) {
            $site = 'GPK';
        }

        $key = $site;
        if (!in_array($site, ['KISS918SHP', 'KISS918H5', 'KISS918H52'])) {
            foreach (['AWC', 'MEGA', 'PUSSY', 'KISS918', 'ABS', 'JOKER', 'YGG', 'DCT', 'KLNS'] as $s) {
                if (substr($site, 0, strlen($s)) === $s) {
                    $key = $s;
                }
            }
        }

        if (empty($jobs[$key])) {
            continue;
        }

        $job = $jobs[$key];
        $status = 'PENDING';
        $statusDateTime = '2021-01-01 00:00:00';

        if (!empty($cron[$mId][$site]['status'])) {
            $status = $cron[$mId][$site]['status'];
        }

        if (!empty($cron[$mId][$site]['status_datetime'])) {
            $statusDateTime = $cron[$mId][$site]['status_datetime'];
        }

        if ($status !== 'PENDING') {
            if ($status === 'PROCESSING' && strtotime($statusDateTime) + 10 * 60 < $currentTimestamp) {

            } else if ($status === 'STARTED' && strtotime($statusDateTime) + 30 * 60 < $currentTimestamp) {

            } else {
                echo "Skipping-{$status}: Merchant {$mId} {$site} job: {$job[1]} with status: {$status} and status datetime: {$statusDateTime}\n";
                continue;
            }
        }

        if (strtotime($statusDateTime) + $job[0] * 60 > $currentTimestamp) {
            continue;
        }

        $data = [
            'nonTransaction' => 1,
            'site'           => $site,
            'cronId'         => $cron[$mId][$site]['id'] ?? null,
        ];

        if ($data['cronId']) {
            $merchantRp->updateCJob($data['cronId'], [
                'status'          => 'PROCESSING',
                'status_datetime' => date('Y-m-d H:i:s')
            ]);
        } else {
            $data['cronId'] = $merchantRp->createCJob([
                'merchant_id'        => $mId,
                'code'               => $site,
                'status'          => 'PROCESSING',
                'execution_datetime' => Carbon::now(),
                'status_datetime'    => Carbon::now(),
            ]);
        }

        if (!in_array($site, ['KISS918SHP', 'KISS918H5', 'KISS918H52'])) {
            foreach (['AWC', 'MEGA', 'PUSSY', 'KISS918', 'ABS', 'JOKER', 'YGG', 'DCT'] as $s) {
                if (str_starts_with($site, $s)) {
                    $siteno = substr($site, strlen($s));
                    if (!empty($siteno)) {
                        $data['siteno'] = $siteno;
                    }
                }
            }
        }

        if (!empty($job[1]) && empty($uniq[$job[1]])) {
            $uniq[$job[1]] = 1;
            $batch[] = [
                "url" => getMerchantServerConfig($mId, 'APIURL'),
                "cronId" => $data['cronId'],
                "merchantId" => $mId,
                "site" => $site,
                "module" => $job[1],
                "accessId" => (int) env('WALLET_SYSTEM_ADMIN_ACCESS_ID'),
                "accessToken" => (string) env('WALLET_SYSTEM_ADMIN_TOKEN'),
                "nonTransaction" => 1
            ];
        }

        if (!empty($job[2]) && empty($uniq[$job[2]])) {
            $uniq[$job[2]] = 1;
            $batch[] = [
                "url" => getMerchantServerConfig($mId, 'APIURL'),
                "cronId" => $data['cronId'],
                "merchantId" => $mId,
                "site" => $site,
                "module" => $job[2],
                "accessId" => (int) env('WALLET_SYSTEM_ADMIN_ACCESS_ID'),
                "accessToken" => (string) env('WALLET_SYSTEM_ADMIN_TOKEN'),
                "nonTransaction" => 1
            ];
        }
    }
}

if (!empty($batch)) {
    echo "Processing Total batch size: " . count($batch) . "\n";
    foreach (array_chunk($batch, 50) as $chunk) {
        selfWalletNileApi('/api/curl/batch', $chunk);
        usleep(100000); // 100ms
    }
    echo "Total batch processed: " . count($batch) . "\n";
}

$file = __FILE__;
echo "Executed script: $file\n";
echo "ExecTime:" . (microtime(true) - $startTime) . "s\n";
