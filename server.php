<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/Bootstrap.php';

use App\Utils\GuzzleUtil;
use Swoole\Runtime;
use Swoole\Http\Server;
use Swoole\Constant;
use App\Utils\RateLimiter;
use App\Utils\Semaphore;

Runtime::enableCoroutine(SWOOLE_HOOK_ALL ^ SWOOLE_HOOK_NATIVE_CURL);

// Env via global env() from Bootstrap
$host      = env('SERVER_HOST', '0.0.0.0');
$port      = (int) env('SERVER_PORT', 9501);
$maxCo     = (int) env('MAX_COROUTINES', 100000);
$maxConc   = (int) env('MAX_CONCURRENCY', 2000);
$globalRps = env('GLOBAL_RPS') !== null ? (float) env('GLOBAL_RPS') : null;

$server = new Server($host, $port, SWOOLE_BASE);
$server->set([
    'max_coroutine'       => $maxCo,
    'worker_num'          => max(1, (int) env('WORKER_NUM', swoole_cpu_num() * 2)),
    'task_worker_num'     => (int) env('TASK_WORKER_NUM', 0),
    'log_level'           => SWOOLE_LOG_INFO,
    'enable_coroutine'    => true,
    'open_http2_protocol' => true,
    'buffer_output_size'  => 64 * 1024 * 1024,
    'socket_buffer_size'  => 8 * 1024 * 1024,
]);

$server->on('Request', function ($req, $res) use ($maxConc, $globalRps) : void {
    $uri    = $req->server['request_uri']    ?? '/';
    $method = $req->server['request_method'] ?? 'GET';

    if ($uri === '/health') {
        $res->header('Content-Type', 'application/json');
        sleep(60);
        $res->end(json_encode(['ok' => true, 'time' => time()]));
        return;
    }

    if ($uri === '/test') {
        $payload = json_decode($req->rawContent() ?: '[]', true) ?: [];
        $id = isset($payload['id']) ? (int)$payload['id'] : 'unknown';

        echo "Running #id " . $id . " - " . date('Y-m-d H:i:s') . "\n";
        $delay = 5;
        sleep($delay); // Simulate some processing delay
        $res->header('Content-Type', 'application/json');
        $res->end(json_encode([
            'message' => 'Test endpoint - received payload',
            'status'  => 'OK',
            'delay'   => $delay,
            '$_POST'    => $payload
        ]));

        echo "Completed #id " . $id . " - " . date('Y-m-d H:i:s') . "\n";
        return;
    }

    // Accept site/module/mids payload; keep /batch alias for convenience
    if ($uri === '/batch/sync_bet' && $method === 'POST') {
        $payload = json_decode($req->rawContent() ?: '[]', true) ?: [];

        $site    = isset($payload['site'])   ? (string)$payload['site']   : '';
        $module  = isset($payload['module']) ? (string)$payload['module'] : '';
        $mids    = isset($payload['mids']) && is_array($payload['mids']) ? $payload['mids'] : [];

        // Validate
        if ($site === '' || $module === '' || empty($mids)) {
            $res->status(400);
            $res->header('Content-Type', 'application/json');
            $res->end(json_encode([
                'error' => 'Invalid payload: require "site" (string), "module" (string), and "mids" (array).'
            ]));
            return;
        }

        // Wallet endpoint & creds from env
        $walletUrl   = env('WALLET_URL') . '/api/v1/index.php';
        $accessId    = env('SYSTEM_ADMIN_ACCESS_ID', '');
        $accessToken = env('SYSTEM_ADMIN_TOKEN', '');
        $cronJobId   = isset($payload['cronJobId']) ? (int)$payload['cronJobId'] : null;

        if ($accessId === '' || $accessToken === '') {
            $res->status(500);
            $res->header('Content-Type', 'application/json');
            $res->end(json_encode([
                'error' => 'SERVER_MISCONFIGURED',
                'message' => 'Missing SYSTEM_ADMIN_ACCESS_ID or SYSTEM_ADMIN_TOKEN in env.'
            ]));
            return;
        }

        // Throttling
        $sem            = new Semaphore($maxConc);
        $globalLimiter  = $globalRps ? new RateLimiter($globalRps, 100) : null;
        $perHostLimiter = [];
        $hostName       = parse_url($walletUrl, PHP_URL_HOST) ?: '';
        $perHostRpsEnv  = env('PER_HOST_RPS'); // optional
        if ($hostName !== '' && $perHostRpsEnv !== null && $perHostRpsEnv !== '') {
            $perHostLimiter[$hostName] = new RateLimiter((float)$perHostRpsEnv, 100);
        }

        // Guzzle helper
        $http = new GuzzleUtil();

        // @TODO: Implement some database - get all jobs tied to $mid and $module
        // @TODO: Throttling - use Semaphore and RateLimiter to limit concurrency and RPS

        $wg      = new Swoole\Coroutine\WaitGroup();
        $results = [];
        $summary  = [
            'total' => count($mids),
            'wait' => false
        ];

        // @TODO: Get cron jobs and store into memory
        foreach ($mids as $mid) {
            $job = [
                'url'  => $walletUrl,
                'data' => array_filter([
                    'module'         => '/betHistory/' . $module, // e.g. /betHistory/jili
                    'accessId'       => $accessId,
                    'accessToken'    => $accessToken,
                    'nonTransaction' => 1,
                    'site'           => $site,
                    'cronJobId'      => $cronJobId,
                ], static fn($v) => $v !== null),
            ];

            $wg->add();
            go(function () use ($mid, $job, $retry, $sem, $globalLimiter, $perHostLimiter, $hostName, $http, &$results, $wg) {
                $sem->acquire();
                try {
                    if ($globalLimiter) $globalLimiter->take();
                    if ($hostName && isset($perHostLimiter[$hostName])) $perHostLimiter[$hostName]->take();

                    $attempt = 0; $resp = null;
                    while ($attempt <= $retry) {
                        $attempt++;
                        $resp = $http->execute('POST',
                            $job['url'],
                            ['Content-Type' => 'application/x-www-form-urlencoded'],
                            $job['data']
                        );

                        if (!isset($resp['error']) && ($resp['status'] >= 200 && $resp['status'] < 500)) {
                            break;
                        }

                        Swoole\Coroutine::sleep(
                            min(1.0 * $attempt, 5.0) * (0.5 + mt_rand() / mt_getrandmax())
                        );

                        // @TODO: Fire HTTP back to worker server to MARK as success
                    }

                    $results[$mid] = $resp;
                } finally {
                    $sem->release();
                    $wg->done();
                }
            });
        }

        // do not wait $wg->wait();
        ksort($results);
        $ok = 0; $errc = 0;
        foreach ($results as $r) isset($r['error']) || ($r['status'] ?? 0) >= 400 ? $errc++ : $ok++;
        $res->header('Content-Type', 'application/json');
        $res->end(json_encode([
            'ok' => $ok,
            'err' => $errc,
            'results' => $results ?: $summary
        ], JSON_UNESCAPED_SLASHES));
        return;
    }

    $res->status(404);
    $res->end('Not found');
});
echo "Swoole HTTP server started at http://{$host}:{$port}\n";
$server->start();