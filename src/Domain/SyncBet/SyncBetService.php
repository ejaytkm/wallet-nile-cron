<?php
declare(strict_types=1);

namespace App\Domain\SyncBet;

use App\Logger\LoggerFactory;
use App\Repositories\Enum\JobTypeEnum;
use App\Repositories\Enum\QueueJobStatusEnum;
use App\Repositories\JobRepo;
use Carbon\Carbon;
use GuzzleHttp\Exception\ConnectException;
use Swoole\Coroutine;

final class SyncBetService
{
    const int MAX_ATTEMPTS = 3;
    const int ATTEMPT_DELAY = 2;

    public function __construct(
        private JobRepo $jobRepo,
    )
    {
    }

    public function reQueue(int $jobId): array
    {
        $job = $this->jobRepo->getQJob($jobId);
        if (!$job) {
            throw new \RuntimeException("Job with ID {$jobId} not found.");
        }

        if (
            $job['status'] === QueueJobStatusEnum::IN_FLIGHT
        ) {
            throw new \RuntimeException("Job with ID {$jobId} is currently in flight and cannot be re-queued.");
        }

        return $this->fireOrQueue(
            (int)$job['payload']['merchantId'],
            (string)$job['payload']['site'],
            (string)$job['payload']['module'],
            (int)$job['cronId'],
            $job
        );
    }

    public function fireOrQueue(
        int $mid,
        string $site,
        string $module,
        int $cronId,
        array $job = []
    ): array
    {
        if (empty($job)) {
            $payload = [
                'merchantId'     => $mid,
                'module'         => '/betHistory/' . $module,
                'accessId'       => (int)env('WALLET_SYSTEM_ADMIN_ACCESS_ID'),
                'accessToken'    => env('WALLET_SYSTEM_ADMIN_TOKEN'),
                'nonTransaction' => 1,
                'site'           => $site,
            ];
            $job = $this->jobRepo->createQJob([
                'type'     => JobTypeEnum::JOB_SYNC_BET,
                "payload"  => $payload,
                "cronId"   => $cronId,
                "status"   => QueueJobStatusEnum::IN_QUEUE,
                "attempts" => 0
            ]);
        }

        if (!self::isCoroutineFull()) {
            $job['status'] = QueueJobStatusEnum::IN_FLIGHT;
            Coroutine::create(function () use ($job) {
                $logger = LoggerFactory::build();
                $now = Carbon::now();
                $payload = $job['payload'];
                $start = microtime(true);
                $jobRepo = $this->jobRepo;

                $jobRepo->updateQJob($job['id'], [
                    'status'   => QueueJobStatusEnum::IN_FLIGHT,
                    'attempts' => $job['attempts'] + 1,
                ]);

                try {
                    $res = selfWalletApi($payload);
                    $parsed = json_decode($res['body'], true);

                    if (isset($parsed['status']) && $parsed['status'] !== 'success') {
                        if ((int)$job['attempts'] >= SyncBetService::MAX_ATTEMPTS) {
                            $job['status'] = QueueJobStatusEnum::FAILED;
                        } else {
                            $job['available_at'] = Carbon::now()->addSeconds(self::ATTEMPT_DELAY);
                            $job['status'] = QueueJobStatusEnum::IN_QUEUE;
                        }
                    } else {
                        $job['status'] = QueueJobStatusEnum::COMPLETED;
                        $job['completed_at'] = Carbon::now();
                    }
                } catch (\Throwable $e) {
                    if ($e instanceof ConnectException && str_contains($e->getMessage(), 'timed out')) {
                        $job['status'] = QueueJobStatusEnum::TIMED_OUT;
                        $job['completed_at'] = Carbon::now();
                    } else {
                        if ((int)$job['attempts'] >= SyncBetService::MAX_ATTEMPTS) {
                            $job['status'] = QueueJobStatusEnum::FAILED;
                        } else {
                            $job['available_at'] = Carbon::now()->addSeconds(self::ATTEMPT_DELAY);
                            $job['status'] = QueueJobStatusEnum::IN_QUEUE;
                        }
                    }

                    $logger->error('SyncBetService - Error processing job', [
                        'errMsg'  => $e->getMessage(),
                        'job' => $job,
                        'payload' => $payload,
                        'exception' => $e->getTrace()
                    ]);
                }

                $id = $job['id'];
                $jobRepo->updateQJob($id, [
                    'status'       => $job['status'],
                    'completed_at' => $job['completed_at'] ?? null,
                    'available_at' => $job['available_at'] ?? null,
                    'duration'     => number_format(microtime(true) - $start),
                ]);

                $cronId = $job['cronId'] ?? 0;
                if ($cronId && in_array($job['status'], [
                        QueueJobStatusEnum::COMPLETED,
                        QueueJobStatusEnum::TIMED_OUT
                    ])) {
                    $jobRepo->updateCJob($cronId, [
                        'status'          => 'PENDING',
                        'status_datetime' => $now
                    ]);
                }
            });
        } else {
            echo "Job ID: {$job['id']} is queued due to coroutine limit.\n";
        }

        return [
            'job' => $job,
        ];
    }

    private static function isCoroutineFull(): bool
    {
        $maxCo = (int)env('MAX_CONCURRENCY');
        $running = Coroutine::stats()['coroutine_num'] ?? 0;

        return $running >= ($maxCo * 0.95);
    }
}