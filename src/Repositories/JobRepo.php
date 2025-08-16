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

    /**
     * @throws MeekroDBException
     */
    public function createQueueJob(
        array $payload,
        ?int $cronJobId,
        string $status,
        ?string $uuid = null
    ): array {
        $uuid = $uuid ?: gen_uuid();
        $this->db->insert('queue_jobs', [
            'payload'      =>self::compressPayload($payload),
            'cron_job_id'  => $cronJobId,
            'status'       => $status,
            'uuid'         => $uuid
        ]);

        $id = (int) $this->db->insertId();
        $row = $this->db->queryFirstRow("SELECT * FROM queue_jobs WHERE id=%i", $id);

        if (!$row) {
            throw new MeekroDBException("Failed to create queue job with ID: $id");
        };

        // HYDRATE TYPES
        $row['id']           = (int) $row['id'];
        $row['attempts']     = (int) $row['attempts'];
        $row['max_attempts'] = (int) $row['max_attempts'];
        $row['cron_job_id']  = $row['cron_job_id'] !== null ? (int) $row['cron_job_id'] : null;

        return $row;
    }

    /**
     * @throws MeekroDBException
     */
    public function updateQueueJob(int $id,array $fields): bool
    {
        if (!$fields) return false;

        // columns allowed by schema
        $allowed = ['uuid','payload','attempts','max_attempts','status','completed_at','cron_job_id'];
        $data = [];

        foreach ($allowed as $col) {
            if (!array_key_exists($col, $fields)) continue;
            $val = $fields[$col];

            if ($col === 'completed_at') {
                if ($val instanceof \DateTimeInterface) {
                    $val = $val->format('Y-m-d H:i:s.u');
                } elseif ($val === '' || $val === false) {
                    $val = null;
                }
            }

            if (in_array($col, ['attempts','max_attempts','cron_job_id'], true)) {
                $val = ($val === null) ? null : (int) $val;
            }

            if ($col === 'payload' && is_array($val)) {
                $val = self::compressPayload($val);
            }

            $data[$col] = $val;
        }

        if (!$data) return false;

        $this->db->update('queue_jobs', $data, 'id=%i', $id);
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

    /**
     * Decompress a payload stored by compressPayload().
     * Returns array if JSON, otherwise string.
     */
    static function decompressPayload(string $blob): array|string|null
    {
        try {
            $bin = base64_decode($blob, true);
            if ($bin === false) {
                // not compressed — fallback to plain
                $plain = $blob;
            } else {
                $plain = gzuncompress($bin);
                if ($plain === false) {
                    // fallback to raw if somehow not compressed
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