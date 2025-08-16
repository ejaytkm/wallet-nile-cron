<?php
declare(strict_types=1);

namespace App\Repositories;

use MeekroDB;
use MeekroDBException;

final class MerchantRepo extends BaseRepository
{
    public function __construct($db = null)
    {
        if ($db) {
            $this->db = $db;
        } else {
            $this->db = new MeekroDB(
                (string) env('DB_MERCHANT_DSN'),
                (string) env('DB_MERCHANT_USER'),
                (string) env('DB_MERCHANT_PASS')
            );
        }
    }
}