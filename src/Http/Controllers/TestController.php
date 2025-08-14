<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Swoole\Http\Response;
use Swoole\Http\Request;

final class TestController
{
    public function __construct()
    {
    }

    public function index(Request $req, Response $res): void
    {
        $response = [
            'status' => 'ok',
        ];

        $res->header('Content-Type', 'application/json');
        $res->end(json_encode($response));
    }
}
