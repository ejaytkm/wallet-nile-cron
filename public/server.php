<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Bootstrap.php';

use App\Config\ServerConfig;
use App\Metrics\RegistryFactory;
use App\Http\HttpServer;

$config = ServerConfig::fromEnv();
[$registry, $metrics] = RegistryFactory::build($config);

$http = new HttpServer($config, $registry, $metrics);
$http->start();