<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database\PDOAbstract;

final class JobsRepository
{
    public function __construct(private PDOAbstract $db) {}

    /**
     * Idempotent enqueue. Provide a stable $jobKey (e.g., hash(site,module,mid)).
     */
    public function enqueue(string $jobKey, array $payload): void
    {
        $sql = "INSERT INTO jobs (job_key, payload_json, status)
                VALUES (:k, :p, 'pending')
                ON DUPLICATE KEY UPDATE payload_json = VALUES(payload_json)";
        $this->db->exec($sql, ['k' => $jobKey, 'p' => json_encode($payload, JSON_UNESCAPED_SLASHES)]);
    }

    /**
     * Atomically claim up to $limit jobs.
     * Returns list of rows with: id, job_key, payload_json
     */
    public function claim(int $limit, string $owner, int $ttlSeconds = 30): array
    {
        // MySQL 8+ (SKIP LOCKED alternative) — simple two-step safe claim
        $ids = $this->db->select(
            "SELECT id
             FROM jobs
             WHERE status='pending'
                OR (status='in_flight' AND (lease_until IS NULL OR lease_until < NOW()))
             ORDER BY id
             LIMIT :n",
            ['n' => $limit]
        );

        if (!$ids) return [];

        $idList = array_map(static fn($r) => (int)$r['id'], $ids);
        $this->db->exec(
            "UPDATE jobs
             SET status='in_flight',
                 attempts=attempts+1,
                 lease_owner=:o,
                 lease_until=DATE_ADD(NOW(), INTERVAL :ttl SECOND),
                 started_at=IFNULL(started_at, NOW())
             WHERE id IN (:ids)
               AND (status='pending' OR (status='in_flight' AND (lease_until IS NULL OR lease_until < NOW())))",
            ['o' => $owner, 'ttl' => $ttlSeconds, 'ids' => $idList]
        );

        // Return the claimed rows
        return $this->db->select(
            "SELECT id, job_key, payload_json
             FROM jobs
             WHERE id IN (:ids) AND lease_owner=:o AND status='in_flight'
             ORDER BY id",
            ['ids' => $idList, 'o' => $owner]
        );
    }

    public function finishOk(int $id, string $owner, int $httpCode = 200, ?int $latencyMs = null): void
    {
        $this->db->exec(
            "UPDATE jobs
             SET status='ok',
                 http_code=:c,
                 latency_ms=:ms,
                 finished_at=NOW(),
                 lease_owner=NULL,
                 lease_until=NULL
             WHERE id=:id AND lease_owner=:o",
            ['c' => $httpCode, 'ms' => $latencyMs, 'id' => $id, 'o' => $owner]
        );
    }

    public function finishError(int $id, string $owner, string $message, ?int $httpCode = null, ?int $latencyMs = null): void
    {
        $this->db->exec(
            "UPDATE jobs
             SET status='error',
                 http_code=:c,
                 latency_ms=:ms,
                 error_message=LEFT(:m,255),
                 finished_at=NOW(),
                 lease_owner=NULL,
                 lease_until=NULL
             WHERE id=:id AND lease_owner=:o",
            ['c' => $httpCode, 'ms' => $latencyMs, 'm' => $message, 'id' => $id, 'o' => $owner]
        );
    }
}