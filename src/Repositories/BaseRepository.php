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

    public function testConnection(): bool
    {
        try {
            // Execute a simple query to test the connection
            $this->db->queryFirstRow("SELECT 1");
            return true; // Connection successful
        } catch (\Exception $e) {
            // Log the error or handle it as needed
            error_log("Database connection failed: " . $e->getMessage());
            return false; // Connection failed
        }
    }
}