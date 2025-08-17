<?php
declare(strict_types=1);

namespace App;

use App\Config\ServerConfig;
use App\Http\Runtime\Shutdown;
use App\Metrics\StatsSampler;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;
use Swoole\Http\Server;
use Swoole\Timer;

final class BaseServer
{
    private Server $server;

    public function __construct(
        private ServerConfig $config,
        private CollectorRegistry $registry,
        private array $m
    ) {
        $debug = env('APP_RELOAD', false);
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

            // DEBUG
            'reload_async'        => $debug ?: null,
            'max_wait_time'       => $debug ? 10 : null,
            'max_request'         => $debug ? null : 10000,
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
                        'message'=>env('APP_DEBUG') ? $e->getMessage() : 'Internal Server Error',
                        'error' => env('APP_DEBUG') ? $e->getTraceAsString() : null
                    ]
                ));
            } finally {
                $code = (string)($res->statusCode ?? 200);
                $this->m['httpReqs']->inc([$method, $code]);
                $this->m['httpLatency']->observe(microtime(true) - $t0, [$method, $code]);
            }
        });

        // Graceful restarts
        $this->server->on('WorkerStart', function ($server, int $workerId) {
            echo "Worker #{$workerId} started.\n";
        });

        $this->server->on('Shutdown', function () {
            echo "Swoole server is shutting down.\n";
            Shutdown::markStopping();
        });

        $this->server->on('WorkerStop', function ($server, int $workerId) {
            echo "Worker #{$workerId} stopped.\n";
            Shutdown::markStopping();

        });

        $this->server->on('WorkerExit', function ($server, int $workerId) {
            echo "Worker #{$workerId} exited.\n";
            Shutdown::markStopping();

        });

        echo "Swoole listening on {$this->config->host}:{$this->config->port}, metrics on {$this->config->metricsHost}:{$this->config->metricsPort}\n";

//        $this->startHotReloadIfDebug();
        $this->server->start();
    }

    public function getServer(): Server
    {
        return $this->server;
    }

    /**
     * @TODO: Fix feature
     */
    private function startHotReloadIfDebug(): void
    {
        $debug = filter_var((string) env('APP_DEBUG', '0'), FILTER_VALIDATE_BOOL);
        if (!$debug) return;

        $dirs = array_filter(array_map('trim', explode(',', (string) env('WATCH_DIRS', 'src,public'))));
        $exts = array_filter(array_map('trim', explode(',', (string) env('WATCH_EXT', 'php,ini'))));
        $intervalMs = (int) env('WATCH_INTERVAL_MS', 5000);

        $last = '';
        Timer::tick($intervalMs, function () use (&$last, $dirs, $exts) {
            $hash = $this->hashDirs($dirs, $exts);
            if ($hash !== $last) {
                echo "\n[Hot Reload] Changes detected, reloading workers...\n";

                $last = $hash;
                $this->server->reload();
            }
        });
    }

    /** Compute a cheap hash of mtimes for selected extensions; ignore heavy dirs. */
    private function hashDirs(array $dirs, array $exts): string
    {
        $sum = 0;
        $extMap = array_flip(array_map('strtolower', $exts));
        $ignore = ['vendor', 'storage', '.git', '.idea', 'node_modules'];

        foreach ($dirs as $root) {
            if (!is_dir($root)) continue;
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                /** @var \SplFileInfo $file */
                $path = $file->getPathname();
                // skip ignored directories fast
                foreach ($ignore as $bad) {
                    if (str_contains($path, DIRECTORY_SEPARATOR . $bad . DIRECTORY_SEPARATOR)) {
                        continue 2;
                    }
                }
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if ($ext === '' || !isset($extMap[$ext])) continue;

                $sum += ($file->getMTime() % 0x7fffffff);
            }
        }
        return (string) $sum;
    }
}