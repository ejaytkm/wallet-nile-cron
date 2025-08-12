<?php
declare(strict_types=1);

namespace App\Utils;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

final class Semaphore {
    private Channel $ch;
    public function __construct(int $max) {
        $this->ch = new Channel($max);
        for ($i=0; $i<$max; $i++) $this->ch->push(true);
    }
    public function acquire(): void { $this->ch->pop(); }
    public function release(): void { $this->ch->push(true); }
}
