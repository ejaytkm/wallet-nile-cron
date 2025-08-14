<?php
declare(strict_types=1);

namespace App\Http;

use App\Config\ServerConfig;
use App\Metrics\StatsSampler;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
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
        $this->server = new Server(
            $config->host,
            $config->port
        );
        $this->server->set([
            'worker_num'          => $config->workerNum,
            'task_worker_num'     => $config->taskWorkerNum,
            'enable_coroutine'    => true,
            'max_coroutine'       => 100000,
            'hook_flags'          => SWOOLE_HOOK_ALL,
            'open_http2_protocol' => true,
            'buffer_output_size'  => 64 * 1024 * 1024,
            'socket_buffer_size'  => 8 * 1024 * 1024,
            'log_level'           => SWOOLE_LOG_INFO,
        ]);

        // metric listener + callback
        $this->server->addlistener(
            $config->metricsHost,
            $config->metricsPort,
            SWOOLE_SOCK_TCP
        );
    }

    public function startWith(callable $handler): void
    {
        $sampler = new StatsSampler($this->server, $this->m, $this->config->workerNum);
        $sampler->start();

        $metricsPort = $this->config->metricsPort;
        // Track counts per worker (no getValue() on Gauge)

        $this->server->on('request', function ($req, $res) use ($metricsPort, $handler) {
            $method  = strtoupper($req->server['request_method'] ?? 'GET');
            $dstPort = (int)($req->server['server_port'] ?? 0);

            $cstats = Coroutine::stats();
            $this->m['coNum']->set((int)($cstats['coroutine_num'] ?? 0));
            $this->m['coPeek']->set((int)($cstats['coroutine_peek_num'] ?? 0));
            $this->m['procRSS']->set(function_exists('memory_get_usage') ? memory_get_usage(true) : 0);

            // :2112 metrics
            if ($dstPort === $metricsPort) {
                $renderer = new RenderTextFormat();
                $res->header('Content-Type', RenderTextFormat::MIME_TYPE);
                return $res->end($renderer->render($this->registry->getMetricFamilySamples()));
            }

            $t0 = microtime(true);

            try {
                $handler($req, $res);
            } catch (\Throwable $e) {
                $res->status(500);
                $res->header('Content-Type','application/json');
                $res->end(json_encode(
                    [
                        'status'=>'error',
                        'error'=>'internal',
                        'message'=>env('APP_DEBUG') ? $e->getMessage() : 'Internal Server Error',
                    ]
                ));
            } finally {
                $code = (string)($res->statusCode ?? 200);
                $this->m['httpReqs']->inc([$method, $code]);
                $this->m['httpLatency']->observe(microtime(true) - $t0, [$method, $code]);
            }
        });

        echo "Swoole listening on {$this->config->host}:{$this->config->port}, metrics on {$this->config->metricsHost}:{$this->config->metricsPort}\n";
        $this->server->start();
    }

    public function getServer(): Server
    {
        return $this->server;
    }
}