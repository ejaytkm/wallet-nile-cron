<?php
declare(strict_types=1);

$startTime = microtime(true);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

use App\Repositories\Enum\JobTypeEnum;
use Carbon\Carbon;

$merchantRp = new App\Repositories\MerchantRepo;
$jobsRp = new App\Repositories\JobRepo();
$wrodb = $merchantRp->getDB();
$jobsdb = $jobsRp->getDB();
$redis = new App\Utils\RedisUtil();
$total_fired = 0;
$jobs = [
    'JILI'  => [5, 'jili'],
    'JILI2' => [5, 'jili'],
    'JILI3' => [5, 'jili']
];
$jobKeys = array_keys($jobs);

$currentTimestamp = strtotime('now');
$cron = [];
$cJobs = $jobsdb->query("SELECT * FROM cron_jobs WHERE type = %s AND code IN %ls", JobTypeEnum::JOB_SYNC_BET, $jobKeys);

foreach ($cJobs as $c) {
    $cron[$c['merchant_id']][$c['code']] = $c;
}

$mIds = $wrodb->queryFirstColumn("SELECT id FROM merchants WHERE status = 'ACTIVE'");

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

        try {
            if ($data['cronId']) {
                $jobsRp->updateCJob($data['cronId'], [
                    'status'          => 'PROCESSING',
                    'status_datetime' => date('Y-m-d H:i:s')
                ]);
            } else {
                $data['cronId'] = $jobsRp->createCJob([
                    'type'               => JobTypeEnum::JOB_SYNC_BET,
                    'status'          => 'PROCESSING',
                    'merchant_id'        => $mId,
                    'code'               => $site,
                    'execution_datetime' => Carbon::now(),
                ]);
            }
        } catch (Exception $e) {
            echo "Error updating or creating cron job for {$mId} {$site}: " . $e->getMessage() . "\n";
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
            try {
                selfWorkerApi('/queue/syncbethistory', [
                    "mid" => $mId,
                    "site" => $site,
                    "module" => $job[1],
                    "cronId" => $data['cronId']
                ]);
                $total_fired++;
            } catch (Exception $e) {
                echo "Error firing job for {$mId} {$site} module {$job[1]}: " . $e->getMessage() . "\n";
            }
        }

        if (!empty($job[2]) && empty($uniq[$job[2]])) {
            $uniq[$job[2]] = 1;
            try {
                selfWorkerApi('/queue/syncbethistory', [
                    "mid" => $mId,
                    "site" => $site,
                    "module" => $job[2],
                    "cronId" => $data['cronId']
                ]);
                $total_fired++;
            } catch (Exception $e) {
                echo "Error firing job for {$mId} {$site} module {$job[2]}: " . $e->getMessage() . "\n";
            }
        }
    }
}

// Roughly 10-20 seconds
// total fired: 1583
echo "Total time taken: " . (microtime(true) - $startTime) . " seconds\n";
echo "Total fired: " . $total_fired . "\n";