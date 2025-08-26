<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/bootstrap.php';

use App\Repositories\Enum\JobTypeEnum;
use Carbon\Carbon;

global $container;

$logger = $container->get(Psr\Log\LoggerInterface::class);
$redis = new App\Utils\RedisUtil();
$startTime = microtime(true);
$currentTimestamp = strtotime('now');

$merchantRp = new App\Repositories\MerchantRepo;
$jobRp = new App\Repositories\JobRepo;
$jobdb = $jobRp->getDB();
$wrodb = $merchantRp->getDB();

$jobs = [
    'JILI'  => [5, 'jili'],
    'JILI2' => [5, 'jili'],
    'JILI3' => [5, 'jili']
];
$jobK = array_keys($jobs);
$cron = [];
$query = "SELECT * FROM cron_jobs WHERE code IN ('" . implode("','", $jobK) . "')";
$cJobs = $jobdb->query($query);

foreach ($cJobs as $c) {
    $cron[$c['merchant_id']][$c['code']] = $c;
}

$sql = env('TEST_MERCHANT_IDS') ?
    "SELECT id FROM merchants WHERE status = 'ACTIVE' AND id IN (" . env('TEST_MERCHANT_IDS') . ")" :
    "SELECT id FROM merchants WHERE status = 'ACTIVE'";
$mIds = $wrodb->queryFirstColumn($sql);

$batch = [];
foreach ($mIds as $mId) {
    $uniq = [];
    $activeSites = getActiveSites($mId);

    foreach ($activeSites as $site) {
        $site = resolveSiteKey($site);
        $key = resolveJobKey($site);

        if (empty($jobs[$key])) {
            continue;
        }

        $job = $jobs[$key];
        $status = $cron[$mId][$site]['status'] ?? 'PENDING';
        $statusDateTime = $cron[$mId][$site]['status_datetime'] ?? '2021-01-01 00:00:00';

        if (shouldSkipJob($status, $statusDateTime, $job[0], $currentTimestamp)) {
            $logger->info("Skipping job for merchant {$mId} site {$site} with status: {$status} and status datetime: {$statusDateTime} and interval: {$job[0]} minutes");
            continue;
        }

        $data = [
            'nonTransaction' => 1,
            'site'           => $site,
            'cronId'         => $cron[$mId][$site]['id'] ?? null,
        ];

        if ($data['cronId']) {
            $jobRp->updateCJob($data['cronId'], [
                'status'          => 'PROCESSING',
                'execution_datetime' => Carbon::now(),
            ]);
        } else {
            $data['cronId'] = $jobRp->createCJob([
                'merchant_id'        => $mId,
                'type'               => JobTypeEnum::JOB_SYNC_BET,
                'code'               => $site,
                'status'             => 'PROCESSING',
                'execution_datetime' => Carbon::now()
            ]);
        }

        if (!empty($job[1]) && empty($uniq[$job[1]])) {
            $uniq[$job[1]] = 1;
            $batch[] = createBatchItem($mId, $site, (int) $data['cronId'], '/betHistory/' . $job[1]);
        }

        if (!empty($job[2]) && empty($uniq[$job[2]])) {
            $uniq[$job[2]] = 1;
            $batch[] = createBatchItem($mId, $site, (int) $data['cronId'], '/betHistory/' . $job[2]);
        }
    }
}

if (!empty($batch)) {
    foreach (array_chunk($batch, 50) as $chunk) {
        selfWalletNileApi('/api/curl/sync-bet-batch', $chunk);
        usleep(100000); // 100ms
    }
}

$logger->info("Executed script: " . __FILE__, [
    'execTime' => number_format(microtime(true) - $startTime, 2) . 's',
    'batchSize' => count($batch),
]);

function getActiveSites(int $merchantId): array {
    global $redis, $wrodb;

    $cacheKey = 'A_SITE:' . $merchantId;
    $sites = $redis->get($cacheKey);

    if (!is_array($sites)) {
        $sites = [];
        $dbSites = $wrodb->queryFirstColumn(
            "SELECT site_name FROM pwd_merchant_site WHERE merchant_id = %i AND status = 'ACTIVE' AND key_1 IS NOT NULL",
            $merchantId
        );
        if (!empty($dbSites)) {
            $since = date('Y-m-d H:i:s', strtotime('-2 hour'));
            $userSites = $wrodb->queryFirstColumn(
                "SELECT DISTINCT site FROM user_site_transaction_log WHERE merchant_id = %i AND created_datetime > %s",
                $merchantId,
                $since
            );
            foreach ($userSites as $site) {
                $tmp = explode('-', $site);
                if (in_array($tmp[0], $dbSites) && !in_array($tmp[0], $sites)) {
                    $sites[] = $tmp[0];
                }
            }
        }
        if (empty($sites)) {
            $sites = $dbSites;
        }
        $redis->set($cacheKey, $sites, 300);
    }

    return $sites;
}

function resolveSiteKey(string $site): string {
    $siteMappings = ['MDBO', 'GMS', 'ETG', 'AVGX', 'SMART', 'ATLAS', 'GPK'];

    foreach ($siteMappings as $mapping) {
        if (str_starts_with($site, $mapping)) {
            return $mapping;
        }
    }

    return $site;
}

function resolveJobKey(string $site): string {
    $jobMappings = ['AWC', 'MEGA', 'PUSSY', 'KISS918', 'ABS', 'JOKER', 'YGG', 'DCT', 'KLNS'];

    foreach ($jobMappings as $mapping) {
        if (str_starts_with($site, $mapping)) {
            return $mapping;
        }
    }

    return $site;
}

function shouldSkipJob(string $status, string $statusDateTime, int $jobInterval, int $currentTimestamp): bool {
    return match (true) {
        $status === 'PROCESSING' && strtotime($statusDateTime) + 10 * 60 < $currentTimestamp => false,
        $status === 'STARTED' && strtotime($statusDateTime) + 30 * 60 < $currentTimestamp => false,
        $status !== 'PENDING' => true,
        default => strtotime($statusDateTime) + $jobInterval * 60 > $currentTimestamp,
    };
}
function createBatchItem(int $merchantId, string $site, int $cronId, string $module): array {
    // "payload" => $payload
    return [
        "url"            => getMerchantServerConfig($merchantId, 'APIURL'),
        "cronId"         => $cronId,
        "merchantId"     => $merchantId,
        "site"           => $site,
        "module"         => $module,
        "accessId"       => (int)env('WALLET_SYSTEM_ADMIN_ACCESS_ID'),
        "accessToken"    => (string)env('WALLET_SYSTEM_ADMIN_TOKEN'),
        "nonTransaction" => 1
    ];
}