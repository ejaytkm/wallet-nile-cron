<?php
declare(strict_types=1);
namespace App\Domain\Jobs;
use App\Database\PDOPool;

final class SyncService {
    public function __construct(private PDOPool $pool) {}
    public function process(string $module, string $site, int $mid): void {
        $pdo = $this->pool->borrow();
        try {
            $stmt = $pdo->prepare('SELECT 1');
            $stmt->execute();
        } finally {
            $this->pool->recycle($pdo);
        }
    }
}
