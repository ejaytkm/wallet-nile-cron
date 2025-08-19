<?php
declare(strict_types=1);

namespace App\Utils;

use Predis\Client;

class FileCacheUtil
{
    private Client $client;

    public function __construct()
    {
    }
}