<?php

return [
    'host' => '0.0.0.0',
    'port' => (int)($_ENV['SWOOLE_PORT'] ?? 9501),

    'database' => [
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => (int)($_ENV['DB_PORT'] ?? 3306),
        'database' => $_ENV['DB_DATABASE'] ?? 'swoole_app',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4',
        'pool_size' => 64, // Connection pool size
    ],

    'throttle' => [
        'max_requests_per_second' => (int)($_ENV['THROTTLE_RPS'] ?? 1000),
    ],
];