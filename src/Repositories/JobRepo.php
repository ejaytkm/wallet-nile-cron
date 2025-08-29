<?php
declare(strict_types=1);

namespace App\Repositories;

use MeekroDB;
use MeekroDBException;

final class JobRepo extends BaseRepository
{
    public function __construct($db = null)
    {
        if ($db) {
            $this->db = $db;
        } else {
            $this->db = new MeekroDB(
                (string) env('DB_GLOBAL_DSN'),
                (string) env('DB_GLOBAL_USER'),
                (string) env('DB_GLOBAL_PASS'),
                [
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                ]
            );
        }
    }

    public function getJobConfigActive($type): array
    {
        $jobs = $this->db->query("SELECT name, json_config FROM cron_jobs_config WHERE type=%s AND enabled = 1", $type);
        return $jobs ?: [];
    }

    public function createCJob($params = []): false|string
    {
        if (empty($params)) {
            return false;
        }

        $this->db->insert('cron_jobs_v2', $params);
        return $this->db->insertId();
    }

    /**
     * @throws MeekroDBException
     */
    public function updateCJob($id, $params = []): bool
    {
        if (empty($params)) {
            return false;
        }

        $d = $this->db->update('cron_jobs_v2', $params, 'id=%i', $id);

        if ($d === false) {
            throw new \MeekroDBException("Failed to update cron job with ID: $id");
        }

        return $this->db->affectedRows() >= 0;
    }
}