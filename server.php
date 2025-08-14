<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/Bootstrap.php';

use Swoole\Http\Server;
use Swoole\Coroutine;
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

/* RR-style compatibility (ss_* namespace) */
$httpQueue    = $reg->getOrRegisterGauge('ss_http', 'requests_queue', 'Queued/in-flight HTTP requests');
$httpWTotal   = $reg->getOrRegisterGauge('ss_http', 'total_workers', 'Total HTTP workers');
$httpWReady   = $reg->getOrRegisterGauge('ss_http', 'workers_ready', 'Workers ready');
$httpWWork    = $reg->getOrRegisterGauge('ss_http', 'workers_working', 'Workers working');
$httpWInvalid = $reg->getOrRegisterGauge('ss_http', 'workers_invalid', 'Workers invalid');
$httpWMem     = $reg->getOrRegisterGauge('ss_http', 'workers_memory_bytes', 'Workers memory (total)');

$jobsWTotal   = $reg->getOrRegisterGauge('ss_jobs', 'total_workers', 'Total job workers');
$jobsWReady   = $reg->getOrRegisterGauge('ss_jobs', 'workers_ready', 'Job workers ready');
$jobsWWork    = $reg->getOrRegisterGauge('ss_jobs', 'workers_working', 'Job workers working');
$jobsWInvalid = $reg->getOrRegisterGauge('ss_jobs', 'workers_invalid', 'Job workers invalid');
$jobsWMem     = $reg->getOrRegisterGauge('ss_jobs', 'workers_memory_bytes', 'Job workers memory (total)');

/* process-style metrics */
$procStart = $reg->getOrRegisterGauge('process', 'start_time_seconds', 'Process start time (unix)');
$procRSS   = $reg->getOrRegisterGauge('process', 'resident_memory_bytes', 'Process RSS bytes');

$start = microtime(true);
$procStart->set((int) $_SERVER['REQUEST_TIME']);

/* server ports */
$host = env('SERVER_HOST', '0.0.0.0');
$port = (int) env('SERVER_PORT', '9501');
$timeoutDefault = (float) env('TARGET_TIMEOUT', 10);

$server = new Server($host, $port);

/* metrics listener on 2112 */
$metricsHost = env('SERVER_HOST', '0.0.0.0');
$metricsPort = 2112;
$server->addlistener($metricsHost, $metricsPort, SWOOLE_SOCK_TCP);

/* worker sizing */
$workerNum = (int) env('WORKER_NUM', swoole_cpu_num());
$httpWTotal->set($workerNum);
$jobsWTotal->set($workerNum);

$inflightCount = 0;

$server->on('request', function (Swoole\Http\Request $req, Swoole\Http\Response $res) use (
    $server, $reg, $httpReqs, $httpInFlight, $httpLatency, $httpUptime,
    $jobsOk, $jobsErr, $jobsDur, $crGauge, $memGauge, $start, $timeoutDefault,
    $httpQueue, $httpWTotal, $httpWReady, $httpWWork, $httpWInvalid, $httpWMem,
    $jobsWTotal, $jobsWReady, $jobsWWork, $jobsWInvalid, $jobsWMem,
    $procRSS, $metricsPort, $workerNum, &$inflightCount
) {
    $method = strtoupper($req->server['request_method'] ?? 'GET');
    $path   = $req->server['request_uri'] ?? '/';
    $dstPort= (int)($req->server['server_port'] ?? 0);

    $httpUptime->set((int)(microtime(true) - $start));
    $crGauge->set(Coroutine::stats()['coroutine_num'] ?? 0);
    $rss = function_exists('memory_get_usage') ? memory_get_usage(true) : 0;
    $memGauge->set($rss);
    $procRSS->set($rss);

    /* metrics-only listener */
    if ($dstPort === $metricsPort) {
        if ($path !== '/metrics') { $res->status(404); return $res->end(); }
        $renderer = new RenderTextFormat();
        $res->header('Content-Type', RenderTextFormat::MIME_TYPE);
        return $res->end($renderer->render($reg->getMetricFamilySamples()));
    }

    /* also expose /metrics on app port for convenience */
    if ($path === '/metrics') {
        $renderer = new RenderTextFormat();
        $res->header('Content-Type', RenderTextFormat::MIME_TYPE);
        $res->end($renderer->render($reg->getMetricFamilySamples()));
        return;
    }

    if ($path === '/health') {
        $res->header('Content-Type', 'text/plain');
        $res->end("ok\n");
        return;
    }

    $t0 = microtime(true);
    $httpInFlight->inc(); $inflightCount++;

    /* update RR-like gauges (approx) */
    $httpQueue->set($inflightCount);
    $working = min($inflightCount, $workerNum);
    $httpWWork->set($working);
    $httpWReady->set(max(0, $workerNum - $working));
    $httpWInvalid->set(0);
    $httpWMem->set($rss);
    $jobsWWork->set($working);
    $jobsWReady->set(max(0, $workerNum - $working));
    $jobsWInvalid->set(0);
    $jobsWMem->set($rss);

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
                        Coroutine::sleep(0.005); // simulate job work
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