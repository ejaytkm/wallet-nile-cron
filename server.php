<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/bootstrap.php';

use App\BaseServer;
use App\Config\ServerConfig;
use App\Http\Kernel;
use App\Http\Router;
use App\Metrics\RegistryFactory;
use Swoole\Http\Server;

$cfg = ServerConfig::fromEnv();
[$registry, $metrics] = RegistryFactory::build($cfg);

$router    = new Router();
$kernel    = new Kernel($router);

$http = new BaseServer($cfg, $registry, $metrics);

Global $container;
$container->set(Server::class, $http->getServer());

$http->startWith(function($req, $res) use ($kernel, $container) {
    $kernel->handle($req, $res, $container);
});