<?php
declare(strict_types=1);

namespace App\Config;

final class ServerConfig
{
    public string $host;
    public int $port;
    public string $metricsHost;
    public int $metricsPort;
    public int $workerNum;
    public int $taskWorkerNum;
    public float $timeoutDefault;

    private function __construct() {}

    public static function fromEnv(): self
    {
        $c = new self();
        $c->host          = (string) env('SERVER_HOST', '0.0.0.0');
        $c->port          = (int) env('SERVER_PORT', '9501');
        $c->metricsHost   = (string) env('METRICS_HOST', '0.0.0.0');
        $c->metricsPort   = 2112;
        $c->workerNum     = (int) env('WORKER_NUM', swoole_cpu_num());
        $c->taskWorkerNum = (int) env('TASK_WORKER_NUM', 0);
        $c->timeoutDefault= (float) env('TARGET_TIMEOUT', 10);
        return $c;
    }
}