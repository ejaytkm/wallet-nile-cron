<?php
declare(strict_types=1);

namespace App\Metrics;

use Prometheus\CollectorRegistry;
use Prometheus\Exception\MetricsRegistrationException;
use Prometheus\Storage\APC;
use Prometheus\Storage\InMemory;

final class RegistryFactory
{
    /**
     * Returns [CollectorRegistry $registry, array $m]
     * $m contains all metric handles keyed by short names.
     * @throws MetricsRegistrationException
     */
    public static function build(object $config): array
    {
        $storage = extension_loaded('apcu') && (ini_get('apc.enabled') || ini_get('apcu.enabled'))
            ? new APC()
            : new InMemory();
        $reg = new CollectorRegistry($storage);

        // HTTP core
        $m['httpReqs']     = $reg->getOrRegisterCounter('ss_http','request_total','HTTP requests',['method','code']);
        $m['httpLatency']  = $reg->getOrRegisterHistogram('ss_http','request_duration_seconds','HTTP request duration',['method','code'],[0.005,0.01,0.025,0.05,0.1,0.25,0.5,1,2,5,10]);
        $m['httpUptime']   = $reg->getOrRegisterGauge('ss_http','uptime_seconds','Server uptime seconds');
        $m['queue']        = $reg->getOrRegisterGauge('ss_http','requests_queue','Queued/in-flight HTTP requests');

        // Workers & connections (stats()-driven)
        $m['workersTotal'] = $reg->getOrRegisterGauge('ss_http','total_workers','Total workers');
        $m['workersActive']= $reg->getOrRegisterGauge('ss_http','workers_active','Active workers');
        $m['workersIdle']  = $reg->getOrRegisterGauge('ss_http','workers_idle','Idle workers');
        $m['userWorkers']  = $reg->getOrRegisterGauge('ss_http','workers_user_total','User workers total');
        $m['taskWorkers']  = $reg->getOrRegisterGauge('ss_http','workers_task_total','Task workers total');

        $m['accept']       = $reg->getOrRegisterCounter('ss_http','accept_total','Accepted connections');
        $m['close']        = $reg->getOrRegisterCounter('ss_http','close_total','Closed connections');
        $m['connections']  = $reg->getOrRegisterGauge('ss_http','connections','Active connections');

        $m['dispatch']     = $reg->getOrRegisterCounter('ss_http','dispatch_total','Dispatch count');
        $m['recvBytes']    = $reg->getOrRegisterCounter('ss_http','total_recv_bytes','Total received bytes');
        $m['sendBytes']    = $reg->getOrRegisterCounter('ss_http','total_send_bytes','Total sent bytes');
        $m['abort']        = $reg->getOrRegisterCounter('ss_http','abort_total','Aborted connections');
        $m['workerConc']   = $reg->getOrRegisterGauge('ss_http','worker_concurrency','Worker concurrency');

        // Memory & coroutines
        $m['procRSS']      = $reg->getOrRegisterGauge('proc','memory_bytes','Process RSS bytes');
        $m['memPerWorker'] = $reg->getOrRegisterGauge('ss_http','memory_per_worker_bytes','Approx memory per worker');
        $m['coNum']        = $reg->getOrRegisterGauge('swoole','coroutines','Active coroutines');
        $m['coPeek']       = $reg->getOrRegisterGauge('swoole','coroutines_peek','Coroutine peek num');

        // Reloads (optional if stats exposes it)
        $m['reloadCount']  = $reg->getOrRegisterCounter('ss_http','reload_count','Reload count');
        $m['lastReload']   = $reg->getOrRegisterGauge('ss_http','latest_reload_timestamp','Latest reload time (unix)');

        // Jobs (kept for /batch/syncbet simulation)
        $m['jobsOk']       = $reg->getOrRegisterCounter('ss_jobs','jobs_ok','Jobs OK',['module','site']);
        $m['jobsErr']      = $reg->getOrRegisterCounter('ss_jobs','jobs_err','Jobs errors',['module','site']);
        $m['jobsDur']      = $reg->getOrRegisterHistogram('ss_jobs','duration_seconds','Job duration',['module','site'],[0.01,0.05,0.1,0.25,0.5,1,2,5,10,30]);

        // Seed static totals
        $m['workersTotal']->set((int)$config->workerNum);
        $m['userWorkers']->set((int)$config->workerNum);
        $m['taskWorkers']->set((int)$config->taskWorkerNum);

        return [$reg, $m];
    }
}