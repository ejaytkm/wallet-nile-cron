<?php
declare(strict_types=1);
namespace App\Http;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;

final class Router {
    private Dispatcher $d;
    public function __construct() {
        $this->d = simpleDispatcher(function (RouteCollector $r) {
            $r->addRoute('GET',  '/health',  [Controllers\HealthController::class, 'index']);
            $r->addRoute('GET',  '/metrics', [Controllers\MetricsController::class, 'expose']);
            $r->addRoute('POST', '/batch/syncbet', [Controllers\JobsController::class, 'syncBet']);
        });
    }
    public function dispatch(string $method, string $uri): array {
        return $this->d->dispatch($method, $uri);
    }
}
