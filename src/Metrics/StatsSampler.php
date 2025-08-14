<?php
declare(strict_types=1);

namespace App\Metrics;

use Swoole\Http\Server;
use Swoole\Timer;

final class StatsSampler
{
    /** @var array<string,int> */
    private array $last = [
        'accept_count'=>0,'close_count'=>0,'dispatch_count'=>0,
        'total_recv_bytes'=>0,'total_send_bytes'=>0,'abort_count'=>0,'reload_count'=>0
    ];

    public function __construct(
        private Server $server,
        private array $m,             // metric handles from RegistryFactory
        private int $workerNum
    ) {}

    public function start(int $periodMs = 1000): void
    {
        Timer::tick($periodMs, function () {
            $s = $this->server->stats();
            $now = time();

            $startTs = (int)($s['start_time'] ?? $now);
            $this->m['httpUptime']->set(max(0, $now - $startTs));

            $total = (int)($s['worker_num'] ?? $this->workerNum);
            $idle  = (int)($s['idle_worker_num'] ?? 0);
            $active= max(0, $total - $idle);

            $this->m['workersTotal']->set($total);
            $this->m['workersIdle']->set($idle);
            $this->m['workersActive']->set($active);

            $this->m['userWorkers']->set((int)($s['user_worker_num'] ?? 0));
            $this->m['taskWorkers']->set((int)($s['task_worker_num'] ?? 0));
            $this->m['connections']->set((int)($s['connection_num'] ?? 0));
            $this->m['workerConc']->set((int)($s['worker_concurrency'] ?? 0));

            $rss = function_exists('memory_get_usage') ? memory_get_usage(true) : 0;
            $this->m['procRSS']->set($rss);
            $this->m['memPerWorker']->set($total > 0 ? intdiv($rss, $total) : 0);

            $this->incDelta('accept_count','accept');
            $this->incDelta('close_count', 'close');
            $this->incDelta('dispatch_count','dispatch');
            $this->incDelta('total_recv_bytes','recvBytes');
            $this->incDelta('total_send_bytes','sendBytes');
            $this->incDelta('abort_count','abort');

            $curReloads = (int)($s['reload_count'] ?? $this->last['reload_count']);
            if ($curReloads > $this->last['reload_count']) {
                $this->m['reloadCount']->incBy($curReloads - $this->last['reload_count']);
                $this->m['lastReload']->set($now);
            }
            $this->last['reload_count'] = $curReloads;
        });
    }

    private function incDelta(string $statKey, string $metricKey): void
    {
        $s = $this->server->stats();
        $cur = (int)($s[$statKey] ?? 0);
        $delta = max(0, $cur - ($this->last[$statKey] ?? 0));
        if ($delta > 0) {
            $this->m[$metricKey]->incBy($delta);
        }
        $this->last[$statKey] = $cur;
    }
}