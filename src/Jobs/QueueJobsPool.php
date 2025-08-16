<?php
declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\JobRepo;
use App\Utils\GuzzleUtil;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\WaitGroup;
use Swoole\Process;
use Swoole\Runtime;

final class QueueJobsPool
{
    private array $cfg;

    public function __construct()
    {
        $this->cfg = [
            'poll_sleep'    => 2,
            'batch'      => (int) env('JOB_LISTENER_BATCH', 100),
            'max_co'     => (int) env('JOB_LISTENER_MAX_CO', 512),
            'lease_sec'  => (int) env('JOB_LISTENER_LEASE_SEC', 60),
            'target_url' => (string) env('WORKER_API_URL', 'http://worker/api/syncbet'),
        ];
    }

    public function process(): Process
    {
        return new Process(function (Process $proc) {
            Runtime::enableCoroutine();
            $http   = new GuzzleUtil();
            $repo   = new JobRepo();
            $batch  = $this->cfg['batch'];
            $poll_sleep = $this->cfg['poll_sleep'];
            $maxCo  = $this->cfg['max_co'];
            $targetUrl = $this->cfg['target_url'];

            $sem = new Channel($maxCo);
            for ($i = 0; $i < $maxCo; $i++) {
                $sem->push(true);
            }

            echo "[job-listener] pid={$proc->pid} started\n";

            while (true) {
                echo "[job-listener] polling for jobs...\n";
                sleep($poll_sleep);
            }
        }, false, SOCK_DGRAM, true);
    }
}