<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database\PDOAbstract;
use Carbon\Carbon;
use PDO;

final class MerchantRepo
{
    public function __construct(
        private ?PDOAbstract $db = null
    ) {
        if ($db === null) {
            $this->db = new PDOAbstract(
                new PDO(
                    env('DB_MERCHANT_DSN'),
                    env('DB_MERCHANT_USER'),
                    env('DB_MERCHANT_PASS')
                )
            );
        }
    }

    public function fetchAllActiveMerchants(): array
    {
        $sql = "SELECT id FROM merchants WHERE status = :status";
        $data = $this->db->select($sql, ['status' => 'ACTIVE'], PDO::FETCH_COLUMN);
        return $data;
    }

    public function fetchAllAcitvePwdForMerchant(): array
    {
        return [];
    }

    /**
     * Fetch distinct active merchant IDs bound to a PWD site,
     * then return (merchant_id, site, start_time) rows limited by time window.
     *
     * @param string $site        e.g. "JILI"
     * @param string $startTime   ISO-8601 string you pass into the downstream job
     * @param int    $hoursBack   how many hours back to scan (default 4)
     */
    public function fetchActiveSitesForMerchant(
        string $site,
        string $startTime,
        int $hoursBack = 2
    ): array {
        if ($site === '') {
            throw new \InvalidArgumentException('Site parameter cannot be empty.');
        }

        // @TODO: Cache this result for performance
        $merchantIds = $this->getActiveMerchantIdsForPwdSites($site);
        if (!$merchantIds) {
            return [];
        }

        $startDate = Carbon::now()->subHours($hoursBack)->format('Y-m-d\TH:i:sP');

        return $this->getMerchantSiteTransactionLogs(
            $merchantIds,
            $site,
            $startDate,
            $startTime
        );
    }

    private function getActiveMerchantIdsForPwdSites(string $site): array
    {
        $sql = <<<SQL
            SELECT DISTINCT m.id
            FROM merchants m
            LEFT JOIN pwd_merchant_site pwd ON m.id = pwd.merchant_id
            WHERE m.status = :ms
              AND pwd.status = :ps
              AND pwd.site_name = :site
              AND pwd.key_1 IS NOT NULL
        SQL;

        $rows = $this->db->select($sql, [
            'ms'   => 'ACTIVE',
            'ps'   => 'ACTIVE',
            'site' => $site,
        ]);

        // fetchColumn() behavior via select(): map to ints
        return array_map(static fn($r) => (int)($r['id'] ?? 0), $rows);
    }

    /**
     * Return the merchants that actually have logs within the time range.
     * Shape: [ [merchant_id => 123, site => 'JILI', start_time => '...'], ... ]
     */
    private function getMerchantSiteTransactionLogs(
        array $merchantIds,
        string $site,
        string $startDateIso,
        string $startTime // echoed back as constant per row, same as your original
    ): array {
        if (!$merchantIds || $site === '' || $startDateIso === '') {
            throw new \InvalidArgumentException('Invalid parameters provided.');
        }

        // Using IN (:ids) with array binding supported by PDOAbstract
        $sql = <<<SQL
            SELECT
                merchant_id,
                site,
                :start_time AS start_time
            FROM user_site_transaction_log ustl
            WHERE merchant_id IN (:ids)
              AND created_datetime >= :start_date
              AND site = :site
            GROUP BY merchant_id, site
            ORDER BY merchant_id, site
        SQL;

        return $this->db->select($sql, [
            'ids'        => $merchantIds,
            'start_date' => $startDateIso,
            'site'       => $site,
            'start_time' => $startTime,
        ]);
    }
}