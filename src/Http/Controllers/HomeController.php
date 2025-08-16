<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Psr\Log\LoggerInterface;
use Swoole\Http\Response;
use Swoole\Http\Request;

final class HomeController
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function index(Request $req, Response $res): void
    {
        $response = [
            "msg" => "Welcome to Nile Cron - proxy"
        ];

        $this->logger->info('HomeController index method called', [
            'request' => [
                'method' => $req->server['request_method'],
                'uri' => $req->server['request_uri'],
                'headers' => $req->header,
                'body' => $req->rawContent(),
            ],
        ]);

        $res->header('Content-Type', 'application/json');
        $res->end(json_encode($response));
    }
}
