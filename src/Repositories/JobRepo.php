<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database\PDOAbstract;
use App\Repositories\Enum\QueueJobStatusEnum;
use PDO;

final class JobRepo
{
    private PDOAbstract $db;
    public function __construct($connection)
    {
        if ($connection instanceof PDO) {
            $this->db = new PDOAbstract($connection);
        } else {
            $this->db = new PDOAbstract(
                new PDO(
                    (string) env('DB_DSN'),
                    (string) env('DB_USER'),
                    (string) env('DB_PASS')
                )
            );
        }
    }

    public function createQueueJob(
        array $payload,
        int $cronId,
        string $status = QueueJobStatusEnum::CREATED
    ) : array
    {
        return [
            'id' => 1, // Example job ID
            'status' => 'created',
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function updateQueueJob($id,  $data): array
    {
        return [
            'id' => $id,
            'status' => 'updated',
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function getCronJob()
    {
        return[
        ];
    }
}