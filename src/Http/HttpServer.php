<?php
declare(strict_types=1);

namespace App\Http;

use App\Config\ServerConfig;
use App\Metrics\StatsSampler;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Swoole\Coroutine\WaitGroup;
use Swoole\Http\Server;
use Swoole\Coroutine;

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

    public function start(): void
    {
        $sampler = new StatsSampler($this->server, $this->m, $this->config->workerNum);
        $sampler->start();

        $metricsPort = $this->config->metricsPort;

        $this->server->on('request', function ($req, $res) use ($metricsPort) {
            $method  = strtoupper($req->server['request_method'] ?? 'GET');
            $path    = $req->server['request_uri'] ?? '/';
            $dstPort = (int)($req->server['server_port'] ?? 0);

            $cstats = Coroutine::stats();
            $this->m['coNum']->set((int)($cstats['coroutine_num'] ?? 0));
            $this->m['coPeek']->set((int)($cstats['coroutine_peek_num'] ?? 0));
            $rss = function_exists('memory_get_usage') ? memory_get_usage(true) : 0;
            $this->m['procRSS']->set($rss);

            if ($dstPort === $metricsPort) {
                $renderer = new RenderTextFormat();
                $res->header('Content-Type', RenderTextFormat::MIME_TYPE);
                return $res->end($renderer->render($this->registry->getMetricFamilySamples()));
            }

            if ($path === '/health') {
                $res->header('Content-Type','text/plain');
                return $res->end("ok\n");
            }

            $t0 = microtime(true);
            $this->m['inflight']->inc();
            $inflight = (int)$this->m['inflight']->getValue(); // lib returns last set; we also keep queue mirrored
            $this->m['queue']->set($inflight);

            try {
                if ($path === '/batch/syncbet' && $method === 'POST') {
                    $payload = json_decode($req->rawContent() ?: '{}', true) ?: [];
                    $site   = (string)($payload['site'] ?? '');
                    $module = (string)($payload['module'] ?? '');
                    $mids   = (array)  ($payload['mids'] ?? []);
                    if ($site === '' || $module === '' || !$mids) {
                        $res->status(400);
                        $res->header('Content-Type','application/json');
                        $res->end(json_encode(['status'=>'error','error'=>'invalid payload']));
                        $this->m['httpReqs']->inc([$method,'400']);
                        $this->m['httpLatency']->observe(microtime(true)-$t0,[$method,'400']);
                        return;
                    }

                    $wg = new WaitGroup();
                    foreach ($mids as $mid) {
                        $wg->add();
                        Coroutine::create(function () use ($wg,$module,$site,$mid) {
                            $t1 = microtime(true);
                            try { Coroutine::sleep(0.005);$this->m['jobsOk']->inc([$module, (string)$site]); }
                            catch (\Throwable) { $this->m['jobsErr']->inc([$module,(string)$site]); }
                            finally { $this->m['jobsDur']->observe(microtime(true)-$t1,[$module,(string)$site]); $wg->done(); }
                        });
                    }
                    $wg->wait();

                    $res->header('Content-Type','application/json');
                    $res->end(json_encode(['status'=>'ok','site'=>$site,'module'=>$module,'count'=>count($mids)]));
                    $this->m['httpReqs']->inc([$method,'200']);
                    $this->m['httpLatency']->observe(microtime(true)-$t0,[$method,'200']);
                    return;
                }

                $res->status(404);
                $res->header('Content-Type','application/json');
                $res->end(json_encode(['status'=>'error','error'=>'not found']));
                $this->m['httpReqs']->inc([$method,'404']);
                $this->m['httpLatency']->observe(microtime(true)-$t0,[$method,'404']);
            } catch (\Throwable) {
                $res->status(500);
                $res->header('Content-Type','application/json');
                $res->end(json_encode(['status'=>'error','error'=>'internal']));
                $this->m['httpReqs']->inc([$method,'500']);
                $this->m['httpLatency']->observe(microtime(true)-$t0,[$method,'500']);
            } finally {
                $this->m['inflight']->dec();
                $inflight = max(0, $inflight - 1);
                $this->m['queue']->set($inflight);
            }
        });

        echo "Swoole listening on {$this->config->host}:{$this->config->port}, metrics on {$this->config->metricsHost}:{$this->config->metricsPort}\n";
        $this->server->start();
    }
}