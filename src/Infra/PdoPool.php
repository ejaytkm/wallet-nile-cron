<?php
declare(strict_types=1);
namespace App\Infra;
use PDO;
use Swoole\Coroutine\Channel;

final class PdoPool {
    private Channel $chan;
    public function __construct(int $size = 32) {
        $this->chan = new Channel($size);
        $dsn  = (string) env('DB_DSN');
        $user = (string) env('DB_USER');
        $pass = (string) env('DB_PASS');
        for ($i = 0; $i < $size; $i++) {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT         => false,
            ]);
            $this->chan->push($pdo);
        }
    }
    public function borrow(): PDO { return $this->chan->pop(); }
    public function recycle(PDO $p): void { $this->chan->push($p); }
}
