<?php

declare(strict_types=1);

namespace App\Repositories;

use MeekroDB;

class BaseRepository
{
    protected MeekroDB $db;

    public function getDB(): MeekroDB
    {
        return $this->db;
    }
}