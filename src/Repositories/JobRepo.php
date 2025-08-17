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
                (string) env('DB_DSN'),
                (string) env('DB_USER'),
                (string) env('DB_PASS')
            );
        }
    }

    public function createCJob($params = []): false|string
    {
        if (empty($params)) {
            return false;
        }

        $requiredFields = ['merchant_id', 'code', 'type'];
        foreach ($requiredFields as $field) {
            if (!isset($params[$field])) {
                throw new \InvalidArgumentException("Missing required field: $field");
            }
        }

        $this->db->insert('cron_jobs', $params);
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

        $d = $this->db->update('cron_jobs', $params, 'id=%i', $id);

        if ($d === false) {
            throw new MeekroDBException("Failed to update cron job with ID: $id");
        }

        return $this->db->affectedRows() >= 0;
    }

    public function getQJob(int $id): ?array
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException("Job ID must be a positive integer");
        }

        $row = $this->db->queryFirstRow(
            "SELECT * FROM queue_jobs WHERE id=%i",
            $id
        );

        if (!$row) {
            return null;
        }

        // HYDRATE TYPES
        $row['id']           = (int) $row['id'];
        $row['attempts']     = (int) $row['attempts'];
        $row['cronId']       = $row['cronId'] !== null ? (int) $row['cronId'] : null;
        $row['payload']      = self::decompressPayload($row['payload']);

        return $row;
    }

    public function createQJob($params = []): array
    {
        if (empty($params)) {
            throw new \InvalidArgumentException("Parameters cannot be empty");
        }

        if (!empty($params['payload'])) {
            $params['payload'] = self::compressPayload($params['payload']);
        }

        $this->db->insert('queue_jobs', $params);

        $row = [
            'id' => (int) $this->db->insertId(),
        ];

        foreach ($params as $key => $value) {
            if ($key === 'payload') {
                $row[$key] = self::decompressPayload($value);
            } else {
                $row[$key] = $value;
            }
        }

        // HYDRATE TYPES
        $row['id']           = (int) $row['id'];
        $row['attempts']     = (int) $row['attempts'];
        $row['cronId']  = $row['cronId'] !== null ? (int) $row['cronId'] : null;

        return $row;
    }

    /**
     * @throws MeekroDBException
     */
    public function updateQJob(int $id,array $params): bool
    {
        if (empty($params)) {
            return false;
        }

        $d = $this->db->update('queue_jobs', $params, 'id=%i', $id);

        if ($d === false) {
            throw new MeekroDBException("Failed to update cron job with ID: $id");
        }

        return $this->db->affectedRows() >= 0;
    }

    static function compressPayload(array|string $payload): string
    {
        // Normalize to string
        $plain = is_array($payload)
            ? json_encode($payload, JSON_UNESCAPED_UNICODE)
            : $payload;

        // gzip compress at max level
        $compressed = gzcompress($plain, 9);

        // Store as base64 so it's text-safe
        return base64_encode($compressed);
    }

    static function decompressPayload(string $blob): array|string|null
    {
        try {
            $bin = base64_decode($blob, true);
            if ($bin === false) {
                // not compressed — fallback to plain
            } else {
                $plain = gzuncompress($bin);
                if ($plain === false) {
                    $plain = $blob;
                }
            }

            // Attempt to parse as JSON
            $arr = json_decode($plain, true);
            return (json_last_error() === JSON_ERROR_NONE) ? $arr : $plain;
        } catch (\Throwable) {
            return null;
        }
    }
}