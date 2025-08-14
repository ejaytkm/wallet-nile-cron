<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Domain\Jobs\SyncService;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;
use Swoole\Http\Response;

final class JobsController {
    public function __construct(private SyncService $svc) {}
    public function syncBet(array $req, Response $res): void {
        $p = $req['json'] ?? [];
        $site   = (string)($p['site'] ?? '');
        $module = (string)($p['module'] ?? '');
        $mids   = (array)  ($p['mids'] ?? []);
        if ($site === '' || $module === '' || !$mids) {
            $res->status(400);
            $res->header('Content-Type','application/json');
            $res->end(json_encode(['status'=>'error','error'=>'invalid payload']));
            return;
        }
        $wg = new WaitGroup();
        foreach ($mids as $mid) {
            $wg->add();
            Coroutine::create(function () use ($wg, $module, $site, $mid) {
                try { $this->svc->process($module, $site, (int)$mid); }
                finally { $wg->done(); }
            });
        }
        $wg->wait();
        $res->header('Content-Type','application/json');
        $res->end(json_encode(['status'=>'ok','site'=>$site,'module'=>$module,'count'=>count($mids)]));
    }
}
