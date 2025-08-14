<?php
declare(strict_types=1);

namespace App\Http;

use FastRoute\Dispatcher;
use Psr\Container\ContainerInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;

final class Kernel
{
    // Add your middleware classes here, e.g.:
    public array $middleware = [
    ];

    public function __construct(private Router $router)
    {
    }

    public function handle(Request $req, Response $res, ContainerInterface $c): void
    {
        $method = strtoupper($req->server['request_method'] ?? 'GET');
        $uri    = $req->server['request_uri'] ?? '/';

        $route  = $this->router->dispatch($method, $uri);
        $status = $route[0];

        if ($status === Dispatcher::METHOD_NOT_ALLOWED) {
            $allowed = $route[1] ?? [];
            $res->status(405);
            $res->header('Content-Type','application/json');
            $res->end(json_encode(['status'=>'error','error'=>'method not allowed','allowed'=>$allowed]));
            return;
        }

        if ($status !== Dispatcher::FOUND) {
            $res->status(404);
            $res->header('Content-Type','application/json');
            $res->end('{"status":"error","error":"not found"}');
            return;
        }

        [$class, $action] = $route[1];
        $core = function () use ($class, $action, $req, $res, $c) {
            $ctrl = $c->get($class);
            return $ctrl->$action($req, $res);
        };

        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn ($next, $mw) => fn () => $mw->process($req, $res, $next),
            $core
        );

        $pipeline();
    }
}
