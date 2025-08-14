<?php
declare(strict_types=1);
namespace App\Http;
use FastRoute\Dispatcher;
use Psr\Container\ContainerInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;

final class Kernel {
    public function __construct(private Router $router) {}
    public function handle(Request $req, Response $res, ContainerInterface $c): void {
        $method = strtoupper($req->server['request_method'] ?? 'GET');
        $uri    = $req->server['request_uri'] ?? '/';
        [$status, $handler, $vars] = $this->router->dispatch($method, $uri);
        if ($status !== Dispatcher::FOUND) {
            $res->status(404);
            $res->header('Content-Type','application/json');
            $res->end('{"status":"error","error":"not found"}');
            return;
        }
        [$class, $action] = $handler;
        $ctrl = $c->get($class);
        $payload = $req->rawContent();
        $input = [
            'json'  => ($payload !== '' ? json_decode($payload, true) : null),
            'query' => $req->get ?? [],
            'vars'  => $vars,
            'method'=> $method,
        ];
        $ctrl->$action($input, $res);
    }
}
