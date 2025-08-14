<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use Swoole\Http\Response;
use Swoole\Http\Request;
use Swoole\Http\Server;

final class HealthController
{
    public function __construct(private Server $server)
    {
    }

    public function index(Request $req, Response $res): void
    {
        $res->header('Content-Type','text/plain');
        $res->end("ok\n");
    }

    public function stats(Request $req, Response $res): void
    {
        $stats = $this->server->stats();
        $res->header('Content-Type','application/json');
        $res->end(json_encode($stats) . "\n");
    }
}