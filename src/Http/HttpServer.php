<?php
declare(strict_types=1);

namespace App\Http;

use App\Config\ServerConfig;
use Prometheus\CollectorRegistry;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;
use Swoole\Http\Server;

final class HttpServer
{
    private Server $server;

    public function __construct(
        private ServerConfig $config,
        private CollectorRegistry $registry,
        private array $m
    ) {
        $this->server = new Server($config->host, $config->port);
        $this->server->addlistener($config->metricsHost, $config->metricsPort, SWOOLE_SOCK_TCP);
    }

    public function startWith(callable $handler): void
    {
        $sampler = new \App\Metrics\StatsSampler($this->server, $this->m, $this->config->workerNum);
        $sampler->start(1000);

        $metricsPort = $this->config->metricsPort;

        $this->server->on('request', function ($req, $res) use ($metricsPort, $handler) {
            $method  = strtoupper($req->server['request_method'] ?? 'GET');
            $dstPort = (int)($req->server['server_port'] ?? 0);

            // live gauges
            $cstats = Coroutine::stats();
            $this->m['coNum']->set((int)($cstats['coroutine_num'] ?? 0));
            $this->m['coPeek']->set((int)($cstats['coroutine_peek_num'] ?? 0));
            $this->m['procRSS']->set(function_exists('memory_get_usage') ? memory_get_usage(true) : 0);

            // serve metrics ONLY on the dedicated listener (:2112)
            if ($dstPort === $metricsPort) {
                $renderer = new \Prometheus\RenderTextFormat();
                $res->header('Content-Type', \Prometheus\RenderTextFormat::MIME_TYPE);
                return $res->end($renderer->render($this->registry->getMetricFamilySamples()));
            }

            // request metrics wrapper
            $t0 = microtime(true);
            $this->m['inflight']->inc();
            $inflight = (int)$this->m['inflight']->getValue();
            $this->m['queue']->set($inflight);

            try {
                // delegate all routes to Kernel/Controllers
                $handler($req, $res);
            } catch (\Throwable $e) {
                $res->status(500);
                $res->header('Content-Type','application/json');
                $res->end(json_encode(['status'=>'error','error'=>'internal']));
            } finally {
                $code = (string)($res->statusCode ?? 200);
                $this->m['httpReqs']->inc([$method, $code]);
                $this->m['httpLatency']->observe(microtime(true) - $t0, [$method, $code]);
                $this->m['inflight']->dec();
                $inflight = max(0, $inflight - 1);
                $this->m['queue']->set($inflight);
            }
        });

        echo "Swoole listening on {$this->config->host}:{$this->config->port}, metrics on {$this->config->metricsHost}:{$this->config->metricsPort}\n";
        $this->server->start();
    }
}