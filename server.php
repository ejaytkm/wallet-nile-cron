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

$httpReqs = $reg->getOrRegisterCounter('ss_http', 'request_total', 'HTTP requests', ['method', 'code']);
$httpInFlight = $reg->getOrRegisterGauge('ss_http', 'requests_inflight', 'In-flight HTTP requests');
$httpLatency = $reg->getOrRegisterHistogram('ss_http', 'request_duration_seconds', 'HTTP request duration', [
    'method', 'code'
], [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2, 5, 10]);
$httpUptime = $reg->getOrRegisterGauge('ss_http', 'uptime_seconds', 'Server uptime seconds');

$jobsOk = $reg->getOrRegisterCounter('ss_jobs', 'jobs_ok', 'Jobs OK', ['module', 'site']);
$jobsErr = $reg->getOrRegisterCounter('ss_jobs', 'jobs_err', 'Jobs errors', ['module', 'site']);
$jobsDur = $reg->getOrRegisterHistogram('ss_jobs', 'duration_seconds', 'Job duration', ['module', 'site'], [
    0.01, 0.05, 0.1, 0.25, 0.5, 1, 2, 5, 10, 30
]);
$crGauge = $reg->getOrRegisterGauge('swoole', 'coroutines', 'Active coroutines');
$memGauge = $reg->getOrRegisterGauge('proc', 'memory_bytes', 'Process RSS bytes');

$start = microtime(true);
$host = env('SERVER_HOST', '0.0.0.0');
$port = (int)env('SERVER_PORT', '9501');
$timeoutDefault = (float)env('TARGET_TIMEOUT', 20);

$server = new Server($host, $port);
$metricsHost = env('SERVER_HOST', '127.0.0.1');
$metricsPort = 2112; // hardcoded as requested
$server->addlistener($metricsHost, $metricsPort, SWOOLE_SOCK_TCP);

// Add $metricsPort to the use() list
$server->on('request', function (Swoole\Http\Request $req, Swoole\Http\Response $res) use (
    $reg, $httpReqs, $httpInFlight, $httpLatency, $httpUptime, $jobsOk, $jobsErr, $jobsDur, $crGauge, $memGauge, $start, $timeoutDefault, $metricsPort
) {
    $method = strtoupper($req->server['request_method'] ?? 'GET');
    $path = $req->server['request_uri'] ?? '/';

    // Serve /metrics only on metrics port 2112
    $dstPort = (int)($req->server['server_port'] ?? 0);
    if ($dstPort === $metricsPort) {
        if ($path !== '/metrics') { $res->status(404); $res->end(); return; }
        $renderer = new RenderTextFormat();
        $res->header('Content-Type', RenderTextFormat::MIME_TYPE);
        $res->end($renderer->render($reg->getMetricFamilySamples()));
        return;
    }

    $httpUptime->set((int)(microtime(true) - $start));
    $crGauge->set(Coroutine::stats()['coroutine_num'] ?? 0);
    if (function_exists('memory_get_usage')) $memGauge->set(memory_get_usage(true));

    if ($path === '/metrics' && $dstPort == $metricsPort) {
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
    $httpInFlight->inc();

    try {
        if ($path === '/batch/syncbet' && $method === 'POST') {
            $raw = $req->rawContent() ?: '';
            $payload = $raw !== '' ? json_decode($raw, true) : [];
            $site = (string)($payload['site'] ?? '');
            $module = (string)($payload['module'] ?? '');
            $mids = (array)($payload['mids'] ?? []);

            $timeout = (float)($payload['timeout'] ?? $timeoutDefault);
            $retry = (int)($payload['retry'] ?? 1);

            if ($site === '' || $module === '' || !$mids) {
                $res->status(400);
                $res->header('Content-Type', 'application/json');
                $res->end(json_encode(['status' => 'error', 'error' => 'invalid payload']));
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
                        Coroutine::sleep(0.005); // simulate
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
            $res->end(json_encode(['status' => 'ok', 'site' => $site, 'module' => $module, 'count' => count($mids)]));

            $httpReqs->inc([$method, '200']);
            $httpLatency->observe(microtime(true) - $t0, [$method, '200']);
            return;
        }

        $res->status(404);
        $res->header('Content-Type', 'application/json');
        $res->end(json_encode(['status' => 'error', 'error' => 'not found']));
        $httpReqs->inc([$method, '404']);
        $httpLatency->observe(microtime(true) - $t0, [$method, '404']);
    } catch (\Throwable) {
        $res->status(500);
        $res->header('Content-Type', 'application/json');
        $res->end(json_encode(['status' => 'error', 'error' => 'internal']));
        $httpReqs->inc([$method, '500']);
        $httpLatency->observe(microtime(true) - $t0, [$method, '500']);
    } finally {
        $httpInFlight->dec();
    }
});

echo "Swoole listening on {$host}:{$port}\n";
$server->start();
