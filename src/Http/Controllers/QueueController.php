<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Domain\SyncBet\SyncBetService;
use Swoole\Http\Request;
use Swoole\Http\Response;

final readonly class QueueController {
    public function __construct(private SyncBetService $syncbet) {}
    public function syncBet(Request $req, Response $res): void {
        if (str_contains($req->header['content-type'], 'application/json')) {
            $req->post = json_decode($req->rawContent(), true);
        }

        $site   = (string) ($req->post['site'] ?? '');
        $module = (string) ($req->post['module'] ?? '');
        $mid   =  $req->post['mid'] ?? [];

        if ($site === '' || $module === '' || !$mid) {
            $res->status(400);
            $res->header('Content-Type','application/json');
            $res->end(json_encode(['status'=>'error','error'=>'invalid payload']));
            return;
        }

        $job = $this->syncbet->fireOrQueue(
            $mid,
            $site,
            $module
        );

        $res->header('Content-Type','application/json');
        $res->end(json_encode([
            'status'=>'OK',
            'message' => 'Job has been queued successfully.',
            'data' => [
                'job' => $job
            ]
        ]));
    }
}
