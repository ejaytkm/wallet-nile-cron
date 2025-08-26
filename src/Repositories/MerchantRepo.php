<?php
declare(strict_types=1);

namespace App\Repositories;

use MeekroDB;

final class MerchantRepo extends BaseRepository
{
    const array WALLET_ENVS = [
        'WALLET_0',
        'WALLET_1'
    ];

    public function __construct(
        $wallet_env = self::WALLET_ENVS[0],
        $db = null
    )
    {
        if ($db) {
            $this->db = $db;
        } else {
            $this->db = new MeekroDB(
                (string) env("DB_{$wallet_env}_DSN"),
                (string) env("DB_{$wallet_env}_USER"),
                (string) env("DB_{$wallet_env}_PASS"),
                [
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                ]
            );
        }
    }
}