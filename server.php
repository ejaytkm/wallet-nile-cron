<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/Bootstrap.php';

use Swoole\Http\Server;
use Swoole\Coroutine;
use Swoole\Timer;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\APC;
use Prometheus\Storage\InMemory;

$storage = extension_loaded('apcu') && ini_get('apc.enabled') ? new APC() : new InMemory();
$reg = new CollectorRegistry($storage);

/* core http metrics */
$httpReqs      = $reg->getOrRegisterCounter('ss_http', 'request_total', 'HTTP requests', ['method','code']);
$httpInFlight  = $reg->getOrRegisterGauge('ss_http', 'requests_inflight', 'In-flight HTTP requests');
$httpLatency   = $reg->getOrRegisterHistogram('ss_http', 'request_duration_seconds', 'HTTP request duration', ['method','code'], [0.005,0.01,0.025,0.05,0.1,0.25,0.5,1,2,5,10]);
$httpUptime    = $reg->getOrRegisterGauge('ss_http', 'uptime_seconds', 'Server uptime seconds');

/* jobs metrics */
$jobsOk  = $reg->getOrRegisterCounter('ss_jobs', 'jobs_ok', 'Jobs OK', ['module','site']);
$jobsErr = $reg->getOrRegisterCounter('ss_jobs', 'jobs_err', 'Jobs errors', ['module','site']);
$jobsDur = $reg->getOrRegisterHistogram('ss_jobs', 'duration_seconds', 'Job duration', ['module','site'], [0.01,0.05,0.1,0.25,0.5,1,2,5,10,30]);

/* gauges */
$crGauge = $reg->getOrRegisterGauge('swoole', 'coroutines', 'Active coroutines');
$memGauge= $reg->getOrRegisterGauge('proc', 'memory_bytes', 'Process RSS bytes');

/* RR-style + stats-based */
$httpQueue    = $reg->getOrRegisterGauge('ss_http', 'requests_queue', 'Queued/in-flight HTTP requests');
$httpWTotal   = $reg->getOrRegisterGauge('ss_http', 'total_workers', 'Total HTTP workers');
$httpWActive  = $reg->getOrRegisterGauge('ss_http', 'workers_active', 'Active HTTP workers');
$httpWIdle    = $reg->getOrRegisterGauge('ss_http', 'workers_idle', 'Idle HTTP workers');
$httpWReady   = $reg->getOrRegisterGauge('ss_http', 'workers_ready', 'Workers ready');      // alias of idle for compat
$httpWWork    = $reg->getOrRegisterGauge('ss_http', 'workers_working', 'Workers working');  // alias of active for compat
$httpWInvalid = $reg->getOrRegisterGauge('ss_http', 'workers_invalid', 'Workers invalid');
$httpWMem     = $reg->getOrRegisterGauge('ss_http', 'workers_memory_bytes', 'Workers memory (total)');

$jobsWTotal   = $reg->getOrRegisterGauge('ss_jobs', 'total_workers', 'Total job workers');
$jobsWReady   = $reg->getOrRegisterGauge('ss_jobs', 'workers_ready', 'Job workers ready');
$jobsWWork    = $reg->getOrRegisterGauge('ss_jobs', 'workers_working', 'Job workers working');
$jobsWInvalid = $reg->getOrRegisterGauge('ss_jobs', 'workers_invalid', 'Job workers invalid');
$jobsWMem     = $reg->getOrRegisterGauge('ss_jobs', 'workers_memory_bytes', 'Job workers memory (total)');

/* process-style + extras from server->stats() */
$procStart = $reg->getOrRegisterGauge('process', 'start_time_seconds', 'Process start time (unix)');
$procRSS   = $reg->getOrRegisterGauge('process', 'resident_memory_bytes', 'Process RSS bytes');

/* connection/traffic counters derived from stats() deltas */
$acceptTotal = $reg->getOrRegisterCounter('ss_http', 'accept_total', 'Accepted connections');
$closeTotal  = $reg->getOrRegisterCounter('ss_http', 'close_total',  'Closed connections');
$connections = $reg->getOrRegisterGauge('ss_http', 'connections',    'Active connections');
$memPerWorker= $reg->getOrRegisterGauge('ss_http', 'memory_per_worker_bytes', 'Approx memory per worker');
$usersTotal  = $reg->getOrRegisterGauge('ss_http', 'workers_user_total', 'User workers total');
$tasksTotal  = $reg->getOrRegisterGauge('ss_http', 'workers_task_total', 'Task workers total');

/* optional reload info placeholders (you can wire real values via signals if needed) */
$reloadCount = $reg->getOrRegisterCounter('ss_http', 'reload_count', 'Reload count');
$lastReload  = $reg->getOrRegisterGauge('ss_http', 'latest_reload_timestamp', 'Latest reload time (unix)');

$start = microtime(true);
$procStart->set((int) ($_SERVER['REQUEST_TIME'] ?? time()));

$host = env('SERVER_HOST', '0.0.0.0');
$port = (int) env('SERVER_PORT', '9501');
$timeoutDefault = (float) env('TARGET_TIMEOUT', 10);

$server = new Server($host, $port);

/* metrics listener on 2112 */
$metricsHost = env('METRICS_HOST', '0.0.0.0');
$metricsPort = 2112;
$server->addlistener($metricsHost, $metricsPort, SWOOLE_SOCK_TCP);

/* sizing */
$workerNum      = (int) env('WORKER_NUM', swoole_cpu_num());
$taskWorkerNum  = (int) env('TASK_WORKER_NUM', 0);

/* expose worker tallies */
$httpWTotal->set($workerNum);
$jobsWTotal->set($workerNum);
$usersTotal->set($workerNum);
$tasksTotal->set($taskWorkerNum);

$inflightCount = 0;

/* 1s sampler: pull server->stats() and update gauges + inc counters by delta */
$lastStats = ['accept_count' => 0, 'close_count' => 0, 'request_count' => 0];
Timer::tick(1000, function() use (
    $server, $reg, $httpUptime, $connections, $acceptTotal, $closeTotal,
    $httpWActive, $httpWIdle, $httpWReady, $httpWWork, $httpWInvalid,
    $jobsWWork, $jobsWReady, $jobsWInvalid, $httpWMem, $jobsWMem, $memPerWorker,
    $procRSS, $workerNum, &$lastStats
) {
    $s = $server->stats();
    $now = time();

    $startTime = (int)($s['start_time'] ?? $now);
    $httpUptime->set(max(0, $now - $startTime));

    $conn = (int)($s['connection_num'] ?? 0);
    $connections->set($conn);

    $acc  = (int)($s['accept_count'] ?? 0);
    $cls  = (int)($s['close_count'] ?? 0);

    $dAcc = max(0, $acc - $lastStats['accept_count']);
    $dCls = max(0, $cls - $lastStats['close_count']);
    if ($dAcc) $acceptTotal->incBy($dAcc);
    if ($dCls) $closeTotal->incBy($dCls);
    $lastStats['accept_count'] = $acc;
    $lastStats['close_count']  = $cls;

    $rss = function_exists('memory_get_usage') ? memory_get_usage(true) : 0;
    $procRSS->set($rss);
    $httpWMem->set($rss);
    $jobsWMem->set($rss);
    $memPerWorker->set($workerNum > 0 ? intdiv($rss, $workerNum) : 0);

    $active = min($conn, $workerNum);
    $idle   = max(0, $workerNum - $active);
    $httpWActive->set($active);
    $httpWIdle->set($idle);
    $httpWReady->set($idle);
    $httpWWork->set($active);
    $httpWInvalid->set(0);
    $jobsWWork->set($active);
    $jobsWReady->set($idle);
    $jobsWInvalid->set(0);
});

$server->on('request', function (Swoole\Http\Request $req, Swoole\Http\Response $res) use (
    $server, $reg, $httpReqs, $httpInFlight, $httpLatency, $httpUptime,
    $jobsOk, $jobsErr, $jobsDur, $crGauge, $memGauge, $start, $timeoutDefault,
    $httpQueue, $httpWWork, $httpWReady, $jobsWWork, $jobsWReady,
    $procRSS, $metricsPort, $workerNum, &$inflightCount
) {
    $method = strtoupper($req->server['request_method'] ?? 'GET');
    $path   = $req->server['request_uri'] ?? '/';
    $dstPort= (int)($req->server['server_port'] ?? 0);

    $httpUptime->set((int)(microtime(true) - $start));
    $crGauge->set(\Swoole\Coroutine::stats()['coroutine_num'] ?? 0);
    $rss = function_exists('memory_get_usage') ? memory_get_usage(true) : 0;
    $memGauge->set($rss);
    $procRSS->set($rss);

    if ($dstPort === $metricsPort) {
        $renderer = new RenderTextFormat();
        $res->header('Content-Type', RenderTextFormat::MIME_TYPE);
        return $res->end($renderer->render($reg->getMetricFamilySamples()));
    }

    if ($path === '/health' || $path === '/healthz') {
        $res->header('Content-Type', 'text/plain');
        $res->end("ok\n");
        return;
    }

    $t0 = microtime(true);
    $httpInFlight->inc(); $inflightCount++;
    $httpQueue->set($inflightCount);

    $working = min($inflightCount, $workerNum);
    $httpWWork->set($working);
    $httpWReady->set(max(0, $workerNum - $working));
    $jobsWWork->set($working);
    $jobsWReady->set(max(0, $workerNum - $working));

    try {
        if ($path === '/batch/syncbet' && $method === 'POST') {
            $raw = $req->rawContent() ?: '';
            $payload = $raw !== '' ? json_decode($raw, true) : [];
            $site   = (string)($payload['site'] ?? '');
            $module = (string)($payload['module'] ?? '');
            $mids   = (array)  ($payload['mids'] ?? []);
            $timeout= (float)  ($payload['timeout'] ?? $timeoutDefault);
            $retry  = (int)    ($payload['retry'] ?? 1);

            if ($site === '' || $module === '' || !$mids) {
                $res->status(400);
                $res->header('Content-Type', 'application/json');
                $res->end(json_encode(['status'=>'error','error'=>'invalid payload']));
                $httpReqs->inc([$method, '400']);
                $httpLatency->observe(microtime(true) - $t0, [$method, '400']);
                return;
            }

            $wg = new Coroutine\WaitGroup();
            foreach ($mids as $mid) {
                $wg->add();
                Coroutine::create(function () use ($wg, $module, $site, $mid, $jobsOk, $jobsErr, $jobsDur) {
                    $t1 = microtime(true);
                    try {
                        Coroutine::sleep(0.005); // simulate work
                        $jobsOk->inc([$module, (string)$site]);
                    } catch (\Throwable) {
                        $jobsErr->inc([$module, (string)$site]);
                    } finally {
                        $jobsDur->observe(microtime(true) - $t1, [$module, (string)$site]);
                        $wg->done();
                    }
                });
            }
            $wg->wait();

            $res->header('Content-Type', 'application/json');
            $res->end(json_encode(['status'=>'ok','site'=>$site,'module'=>$module,'count'=>count($mids)]));
            $httpReqs->inc([$method, '200']);
            $httpLatency->observe(microtime(true) - $t0, [$method, '200']);
            return;
        }

        $res->status(404);
        $res->header('Content-Type', 'application/json');
        $res->end(json_encode(['status'=>'error','error'=>'not found']));
        $httpReqs->inc([$method, '404']);
        $httpLatency->observe(microtime(true) - $t0, [$method, '404']);
    } catch (\Throwable) {
        $res->status(500);
        $res->header('Content-Type', 'application/json');
        $res->end(json_encode(['status'=>'error','error'=>'internal']));
        $httpReqs->inc([$method, '500']);
        $httpLatency->observe(microtime(true) - $t0, [$method, '500']);
    } finally {
        $httpInFlight->dec(); $inflightCount = max(0, $inflightCount - 1);
        $httpQueue->set($inflightCount);
        $working = min($inflightCount, $workerNum);
        $httpWWork->set($working);
        $httpWReady->set(max(0, $workerNum - $working));
        $jobsWWork->set($working);
        $jobsWReady->set(max(0, $workerNum - $working));
    }
});

echo "Swoole listening on {$host}:{$port}, metrics on {$metricsHost}:{$metricsPort}\n";
$server->start();