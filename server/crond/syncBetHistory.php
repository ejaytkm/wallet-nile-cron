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
$currentTimestamp = strtotime('now') + 1;
$wallet_env = match (true) {
    isset($_GET['wEnv']) && in_array($_GET['wEnv'], App\Repositories\MerchantRepo::WALLET_ENVS) => $_GET['wEnv'],
    isset($_POST['wEnv']) && in_array($_POST['wEnv'], App\Repositories\MerchantRepo::WALLET_ENVS) => $_POST['wEnv'],
    env('wEnv') !== null && in_array(env('wEnv'), App\Repositories\MerchantRepo::WALLET_ENVS) => env('wEnv'),
    default => null
};

if (empty($wallet_env)) {
    $logger->error("Invalid wallet environment passed. Please see config at repo", ['wEnv' => $_GET['wEnv'] ?? null]);
    exit;
}

$merchantRp = new App\Repositories\MerchantRepo($wallet_env);
$globalRp = new App\Repositories\JobRepo();
$glodb = $globalRp->getDB();
$wrodb = $merchantRp->getDB();

$config = $globalRp->getJobConfigActive(JobTypeEnum::JOB_SYNC_BET);
if (empty($config)) {
    $logger->info("No active job config found for " . JobTypeEnum::JOB_SYNC_BET);
    exit;
}

$jobs = [];
foreach ($config as $c) {
    $jobs[$c['name']] = json_decode($c['json_config'], true);
}
$jobK = array_keys($jobs);
$query = "SELECT * FROM cron_jobs_v2 WHERE code IN ('" . implode("','", $jobK) . "')";

$cron = [];
foreach ($glodb->query($query) as $c) {
    $cron[$c['merchant_id']][$c['code']] = $c;
}

$sql = "SELECT id FROM merchants WHERE status = 'ACTIVE'";
//$sql = "SELECT id FROM merchants WHERE status = 'ACTIVE' AND (id BETWEEN 1 AND 71 OR id BETWEEN 1001 AND 1370);"; // revert back to testing
$mIds = $wrodb->queryFirstColumn($sql);

$batch = [];
$skipped = 0;
foreach ($mIds as $mId) {
    $uniq = [];
    $activeSites = getActiveSites($mId);

    foreach ($activeSites as $site) {
        $site = resolveSiteKey($site);
        $key = resolveJobKey($site);
        $job = $cron[$mId][$site] ?? null;
        $jobC = $jobs[$key] ?? null;

        if (empty($jobs[$key])) {
            continue;
        }

        if (shouldSkipJob($job, $jobC)) {
            $skipped++;
            $logger->info("Skipping job for $site of merchant $mId", [
                'time' => $currentTimestamp,
                'job' => $job,
            ]);
            continue;
        }

        $data = [
            'site'           => $site,
            'nonTransaction' => 1,
        ];

        if (isset($job['id'])) {
            $data['cronId'] = $job['id'];
            $globalRp->updateCJob($data['cronId'], [
                'execution_datetime' => Carbon::now(),
                'status'             => 'PROCESSING'
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

        $modules = $jobC['module'] ?? [];
        foreach ($modules as $modName) {
            if (isset($uniq[$site])) {
                continue;
            }

            $uniq[$site] = 1;
            $batch[] = createBatchItem(
                $mId,
                $site,
                (int)$data['cronId'],
                '/betHistory/' . $modName
            );
        }
    }
}

if (!empty($batch)) {
    foreach (array_chunk($batch, 50) as $index => $chunk) {
        $res = selfWalletNileApi('/api/curl/sync-bet-batch', $chunk, $wallet_env, true);
        $logger->info("Success Response Chunk:", [
            'env'    => $wallet_env,
            'iteration' => $index + 1,
            'result' => $res['body'] ?? 'Cannot determine results'
        ]);
        usleep(100000); // 100ms
    }
}

$logger->info("Completed SyncBet:", [
    'summary' => [
    'execTime' => number_format(microtime(true) - $startTime, 2),
    'env'      => $wallet_env,
    'keys'     => 'sqs_cron_default:' . $wallet_env . ':{site}_{merchantId}',
    'config'   => $jobK,
    'merchantCount'=> count($mIds),
    'fired'    => count($batch),
    'skipped'  => $skipped
]]);

function getActiveSites(int $merchantId): array
{
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
            $since = date('Y-m-d H:i:s', strtotime('-1 hour'));
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
        if (!empty($sites)) {
            $redis->set($cacheKey, $sites, 300 + rand(0,5));
        }
    }

    return $sites;
}

function resolveSiteKey(string $site): string
{
    $siteMappings = ['MDBO', 'GMS', 'ETG', 'AVGX', 'SMART', 'ATLAS', 'GPK'];

    foreach ($siteMappings as $mapping) {
        if (str_starts_with($site, $mapping)) {
            return $mapping;
        }
    }

    return $site;
}

function resolveJobKey(string $site): string
{
    $jobMappings = ['AWC', 'MEGA', 'PUSSY', 'KISS918', 'ABS', 'JOKER', 'YGG', 'DCT', 'KLNS'];

    foreach ($jobMappings as $mapping) {
        if (str_starts_with($site, $mapping)) {
            return $mapping;
        }
    }

    return $site;
}

function shouldSkipJob($job, $jobC): bool
{
    global $currentTimestamp;

    $status = $job['status'] ?? '';
    $statusDateTime = $job['status_datetime'] ?? '';
    $executionDateTime = $job['execution_datetime'] ?? '';
    $jobInterval = (int) $jobC['interval'] ?? 60; // 60seconds default interval

    if (empty($statusDateTime)) {
        return strtotime($executionDateTime) + $jobInterval > $currentTimestamp;
    }

    if ($status !== 'PENDING') {
        if ($status === 'PROCESSING' && strtotime($statusDateTime) + 10*60 < $currentTimestamp) { // over 10minutes of `processing` no response

        } else if ($status === 'STARTED' && strtotime($statusDateTime) + 20*60 < $currentTimestamp) { // over 20minutes no uncompleted processes

        } else {
            return true;
        }
    }

    if (strtotime($statusDateTime) + $jobInterval > $currentTimestamp) {
        return true;
    }

    return false;
}

function createBatchItem(int $merchantId, string $site, int $cronId, string $module): array
{
    return [
        "url"            => getMerchantServerConfig($merchantId, 'CRONURL'),
        "cronId"         => $cronId,
        "merchantId"     => $merchantId,
        "site"           => $site,
        "module"         => $module,
        "accessId"       => (int)env('WALLET_SYSTEM_ADMIN_ACCESS_ID'),
        "accessToken"    => (string)env('WALLET_SYSTEM_ADMIN_TOKEN'),
        "nonTransaction" => 1
    ];
}