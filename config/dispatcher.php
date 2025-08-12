<?php

return [
    'database' => [
        'host' => $_ENV['DB_HOST'] ?? 'host.docker.internal',
        'port' => (int)($_ENV['DB_PORT'] ?? 3306),
        'database' => $_ENV['DB_DATABASE'] ?? 'swoole_app',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? 'rootpassword',
        'charset' => 'utf8mb4',
        'pool_size' => 16,
    ],

    'worker_nodes' => [
        ['host' => '10.0.1.10', 'port' => 9501],
        ['host' => '10.0.1.11', 'port' => 9501],
        ['host' => '10.0.1.12', 'port' => 9501],
        // Add more worker nodes as needed
    ],
];