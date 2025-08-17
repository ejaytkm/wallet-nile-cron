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
        $maxCo = (int)env('MAX_CONCURRENCY');
        $running = Coroutine::stats()['coroutine_num'] ?? 0;

        if (empty($job)) {
            $payload = [
                'merchantId'      => $mid,
                'module'          => '/betHistory/' . $module,
                'accessId'       => (int) env('WALLET_SYSTEM_ADMIN_ACCESS_ID'),
                'accessToken'    => env('WALLET_SYSTEM_ADMIN_TOKEN'),
                'nonTransaction' => 1,
                'site'            => $site,
            ];
            $job = $this->jobRepo->createQJob([
                'type'     => JobTypeEnum::JOB_SYNC_BET,
                "payload"  => $payload,
                "cronId"   => $cronId,
                "status"   => QueueJobStatusEnum::IN_QUEUE,
                "attempts" => 0
            ]);
        }

        if ($running < ($maxCo * 0.9)) {
            $job['status'] = QueueJobStatusEnum::IN_FLIGHT;
            Coroutine::create(function () use ($job) {
                $jobRepo = new JobRepo();
                $logger = LoggerFactory::build();
                $now = Carbon::now();
                $payload = $job['payload'];
                $start = microtime(true);

                try {
                    $res = selfWalletApi($payload);

                    // WALLET SERVER - Response Validations
                    $parsed = json_decode($res['body'], true);
                    if (isset($parsed['status']) && $parsed['status'] !== 'success') {
                        $logger->error('SyncBetService::fireOrQueue - Invalid response from SelfWalletApi', [
                            'job_id'   => $job['id'],
                            'response' => $res['body']
                        ]);
                        throw new \RuntimeException(
                            'Invalid response from SelfWalletApi: ' . $res['body']
                        );
                    }
                    $job['status'] = QueueJobStatusEnum::COMPLETED;
                } catch (ConnectException $e) {
                    $job['status'] = str_contains($e->getMessage(), 'timed out')
                        ? QueueJobStatusEnum::TIMED_OUT
                        : QueueJobStatusEnum::FAILED;
                } catch (\Throwable $e) {
                    // @TODO: How do we handle retries - put back and fire into maybe this coroutine again?
                    $job['status'] = QueueJobStatusEnum::FAILED;
                    $logger->error(' Error processing job', [
                        'job_id' => $job['id'],
                        'error'  => $e->getMessage(),
                        'trace'  => $e->getTraceAsString(),
                        'payload' => $payload
                    ]);
                }

                $id = $job['id'];
                $jobRepo->updateQJob($id, [
                    'status'       => $job['status'],
                    'attempts'     => $job['attempts'] + 1,
                    'completed_at' => $now,
                    'duration'     => number_format(microtime(true) - $start, 2),
                ]);

                $cronId = $job['cronId'] ?? 0;
                if (
                    $cronId !== 0 &&
                    in_array($job['status'], [
                        QueueJobStatusEnum::COMPLETED,
                        QueueJobStatusEnum::TIMED_OUT
                    ])
                ) {
                    $jobRepo->updateCJob($cronId, [
                        'status'          => 'PENDING',
                        'status_datetime' => $now
                    ]);
                }

                $logger->info("Job ID: {$id} processed successfully with status: {$job['status']}", [
                    'job_id'  => $id,
                    'cron_id' => $cronId,
                    'status'  => $job['status'],
                    'payload' => $payload
                ]);
            });
        } else {
            $logger = LoggerFactory::build();
            $logger->info("No Running: $running, Max Concurrency: $maxCo. Skipping job:" . $job['id']
            );
        }

        return [
            'job' => $job,
        ];
    }
}