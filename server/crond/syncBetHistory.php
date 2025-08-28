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
$wallet_env = match (true) {
    isset($_GET['wEnv']) && in_array($_GET['wEnv'], App\Repositories\MerchantRepo::WALLET_ENVS) => $_GET['wEnv'],
    isset($_POST['wEnv']) && in_array($_POST['wEnv'], App\Repositories\MerchantRepo::WALLET_ENVS) => $_POST['wEnv'],
    env('wEnv') !== null && in_array(env('wEnv'), App\Repositories\MerchantRepo::WALLET_ENVS) => env('wEnv'),
    default => null
};

if (empty($wallet_env)) {
    $logger->error("Invalid wallet environment passed. Please see config at repo", ['wenv' => $_GET['wenv'] ?? null]);
    exit;
}

$merchantRp = new App\Repositories\MerchantRepo($wallet_env);
$globalRp = new App\Repositories\JobRepo();
$glodb = $globalRp->getDB();
$wrodb = $merchantRp->getDB();

$config = $globalRp->getJobsConfig(JobTypeEnum::JOB_SYNC_BET);
$jobs = [];
foreach ($config as $c) {
    $jobs[$c['job_name']] = json_decode($c['json_config'], true);
}
$jobK = array_keys($jobs);
$query = "SELECT * FROM cron_jobs_v2 WHERE code IN ('" . implode("','", $jobK) . "')";

$cron = [];
foreach ($glodb->query($query) as $c) {
    $cron[$c['merchant_id']][$c['code']] = $c;
}

$sql = env('TEST_MERCHANT_IDS') ?
    "SELECT id FROM merchants WHERE status = 'ACTIVE' AND id IN (" . env('TEST_MERCHANT_IDS') . ")" :
    "SELECT id FROM merchants WHERE status = 'ACTIVE'";
$mIds = $wrodb->queryFirstColumn($sql);

$batch = [];
$skipped = 0;
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
            $skipped++;
            continue;
        }

        $data = [
            'nonTransaction' => 1,
            'site'           => $site,
            'cronId'         => $cron[$mId][$site]['id'] ?? null,
        ];

        if ($data['cronId']) {
            $globalRp->updateCJob($data['cronId'], [
                'status'          => 'PROCESSING',
                'execution_datetime' => Carbon::now(),
            ]);
        } else {
            $data['cronId'] = $globalRp->createCJob([
                'env'                => $wallet_env,
                'type'               => JobTypeEnum::JOB_SYNC_BET,
                'merchant_id'        => $mId,
                'code'               => $site,
                'execution_datetime' => Carbon::now(),
                'status'             => 'PROCESSING'
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
//        selfWalletNileApi('/api/curl/sync-bet-batch', $chunk);
        usleep(100000); // 100ms
    }
}

$logger->info("Executed script: " . __FILE__, [
    'execTime' => number_format(microtime(true) - $startTime, 2) . 's',
    'env' => $wallet_env,
    'jobsKey' => count($jobK),
    'processed' => count($batch),
    'skipped' => $skipped
]);

function getActiveSites(int $merchantId): array {
    global $redis, $wrodb, $wallet_env;

    $cacheKey = $wallet_env . ':A_SITE:' . $merchantId;
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
        default => strtotime($statusDateTime) + $jobInterval > $currentTimestamp,
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