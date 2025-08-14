<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Bootstrap.php';

use App\Config\ServerConfig;
use App\Container\ContainerFactory;
use App\Http\HttpServer;
use App\Http\Kernel;
use App\Http\Router;
use App\Metrics\RegistryFactory;

$cfg = ServerConfig::fromEnv();
[$registry, $metrics] = RegistryFactory::build($cfg);

$container = ContainerFactory::build();
$router    = new Router();
$kernel    = new Kernel($router);

$http = new HttpServer($cfg, $registry, $metrics);
$http->startWith(function($req, $res) use ($kernel, $container) {
    $kernel->handle($req, $res, $container);
});