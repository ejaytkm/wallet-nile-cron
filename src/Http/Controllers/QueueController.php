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
        $merchantId   =  (int) $req->post['mid'] ?? [];
        $cronId = (int) $req->post['cronId'] ?? 0;

        if ($site === '' || $module === '' || !$merchantId) {
            $res->status(400);
            $res->header('Content-Type','application/json');
            $res->end(json_encode(['status'=>'error','error'=>'invalid payload']));
            return;
        }

        $jobData = $this->syncbet->fireOrQueue(
            $merchantId,
            $site,
            $module,
            $cronId
        );

        $res->header('Content-Type','application/json');
        $res->end(json_encode([
            'status'=>'OK',
            'message' => 'Job has been queued successfully.',
            'data' =>$jobData
        ]));
    }

    public function syncBetRequeue(Request $req, Response $res): void {
        if (str_contains($req->header['content-type'], 'application/json')) {
            $req->post = json_decode($req->rawContent(), true);
        }

        $jobId = (int) ($req->post['jobId'] ?? 0);

        if (!$jobId) {
            $res->status(400);
            $res->header('Content-Type','application/json');
            $res->end(json_encode(['status'=>'error','error'=>'invalid job ID']));
            return;
        }

        $jobData = $this->syncbet->reQueue($jobId);

        $res->header('Content-Type','application/json');
        $res->end(json_encode([
            'status'=>'OK',
            'message' => 'Job has been re-queued successfully.',
            'data' =>$jobData
        ]));
    }
}
