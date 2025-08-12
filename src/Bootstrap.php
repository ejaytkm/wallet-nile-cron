<?php
use Dotenv\Dotenv;

function env(string $key, $default = null)
{
    return $_ENV[$key] ?? getenv($key) ?? $default;
}

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();