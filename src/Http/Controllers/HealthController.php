<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use Swoole\Http\Response;

final class HealthController {
    public function index(array $_, Response $res): void {
        $res->header('Content-Type','text/plain');
        $res->end("ok\n");
    }
}
