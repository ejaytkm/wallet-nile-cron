<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database\PDOAbstract;

final class JobRepo
{
    public function __construct(
        private ?PDOAbstract $db = null
    ) {
        if ($db === null) {
            $this->db = new PDOAbstract(
                new \PDO(
                    env('DB_DSN'),
                    env('DB_USER'),
                    env('DB_PASS')
                )
            );
        }
    }

    public function getActiveCronJobs(): array
    {
    }
}