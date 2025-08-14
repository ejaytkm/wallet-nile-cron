<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

use App\Repositories\JobRepo;
use App\Repositories\MerchantRepo;

$merchantRp = new MerchantRepo();
$jobsRp = new JobRepo();

$jobsConfig= [
    'JILI'  => [5, 'jili'],
    'JILI2' => [5, 'jili'],
    'JILI3' => [5, 'jili']
];

# current: 2952 merchants to loop and fire
$mIds = $merchantRp->fetchAllActiveMerchants();
foreach ($mIds as $mId) {
    $uniq = [];
    $cacheKey = 'USEDSITE-' . $mId;

    // @TODO: Extract this into a separate function
    $as = cacheDataFile($cacheKey); // TODO: redis cache
    if (!is_array($as)) {
        $as = [];

        // @TODO
        die('ok test');

        $sites = $wrodb->queryFirstColumn("SELECT site_name FROM pwd_merchant_site WHERE merchant_id = %i AND status = 'ACTIVE' AND key_1 IS NOT NULL", $mId);
        if (!empty($sites)) {
            $mDT = date('Y-m-d H:i:s', strtotime('-6 hour'));
            $us = $wrodb->queryFirstColumn("SELECT DISTINCT site FROM user_site_transaction_log WHERE merchant_id = %i AND created_datetime > %s", $mId, $mDT);
            foreach ($us as $s) {
                $tmp = explode('-', $s);
                if (in_array($tmp[0], $sites) && !in_array($tmp[0], $as)) {
                    $as[] = $tmp[0];
                }
            }
        }
        cacheDataFile($cacheKey, $as, 300);
    }

    // @TODO: Extract this into a separate function
    die('ok test');

    foreach ($as as $site) {
        if (strtotime($statusDateTime) + $job[0] * 60 > $currentTimestamp) {
            continue;
        }
        $data = ['nonTransaction' => 1, 'site' => $site];
        if (!empty($cron[$mId][$site]['id'])) {
            $data['cronJobId'] = $cron[$mId][$site]['id'];
            DB::update('cron_jobs', [
                'status' => 'PROCESSING', 'status_datetime' => $NOW
            ], 'id = %i', $data['cronJobId']);
        } else {
            DB::insert('cron_jobs', [
                'merchant_id'     => $mId, 'code' => $site, 'execution_datetime' => $NOW, 'status' => 'PROCESSING',
                'status_datetime' => $NOW
            ]);
            $data['cronJobId'] = DB::insertId();
        }
        if (!in_array($site, ['KISS918SHP', 'KISS918H5', 'KISS918H52'])) {
            foreach (['AWC', 'MEGA', 'PUSSY', 'KISS918', 'ABS', 'JOKER', 'YGG', 'DCT'] as $s) {
                if (substr($site, 0, strlen($s)) === $s) {
                    $siteno = substr($site, strlen($s));
                    if (!empty($siteno)) {
                        $data['siteno'] = $siteno;
                    }
                }
            }
        }

        if (!empty($job[1]) && empty($uniq[$job[1]])) {
            $uniq[$job[1]] = 1;
            echo "Processing job for merchant $mId, site $site, job type {$job[1]}\n";
//            selfAPI($mId, '/betHistory/' . $job[1], $data, true);
        }
        if (!empty($job[2]) && empty($uniq[$job[2]])) {
            $uniq[$job[2]] = 1;
            echo "Processing job for merchant $mId, site $site, job type {$job[2]}\n";
//            selfAPI($mId, '/betHistory/' . $job[2], $data, true);
        }
    }

    // @TODO: Batch selfAPI to our
}

# sync_bet_history will fire calls to worker to START cronjobs