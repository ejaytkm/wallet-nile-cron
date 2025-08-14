<?php
declare(strict_types=1);

use Swoole\Timer;

require __DIR__ . '/vendor/autoload.php';

// Task 1: Run every 5 seconds
Timer::tick(5000, function () {
    echo "[" . date('Y-m-d H:i:s') . "] Task 1 executed.\n";
    // Add your task logic here
});

// Task 2: Run every 10 seconds
Timer::tick(10000, function () {
    echo "[" . date('Y-m-d H:i:s') . "] Task 2 executed.\n";
    // Add your task logic here
});

// Optional: Stop a timer after a certain period
$timerId = Timer::tick(15000, function () {
    echo "[" . date('Y-m-d H:i:s') . "] Task 3 executed.\n";
    // Add your task logic here
});

// Stop Task 3 after 1 minute
Timer::after(60000, function () use ($timerId) {
    Timer::clear($timerId);
    echo "[" . date('Y-m-d H:i:s') . "] Task 3 stopped.\n";
});

// Keep the script running
echo "Swoole cron-like tasks started.\n";
Swoole\Event::wait();