<?php
declare(strict_types=1);

namespace App\Utils;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

final class RateLimiter {
    private float $rps;
    private int $burst;
    private float $tokens;
    private float $last;
    public function __construct(float $rps, int $burst = 50) {
        $this->rps = max(0.0001, $rps);
        $this->burst = max(1, $burst);
        $this->tokens = $burst;
        $this->last = microtime(true);
    }
    public function take(): void {
        while (true) {
            $now = microtime(true);
            $elapsed = $now - $this->last;
            $this->last = $now;
            $this->tokens = min($this->burst, $this->tokens + $elapsed * $this->rps);
            if ($this->tokens >= 1) { $this->tokens -= 1; return; }
            Coroutine::sleep(max(0.0005, (1 - $this->tokens) / $this->rps));
        }
    }
}