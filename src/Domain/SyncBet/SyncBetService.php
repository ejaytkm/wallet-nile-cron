<?php
declare(strict_types=1);

namespace App\Domain\SyncBet;

use App\Repositories\Enum\QueueJobStatusEnum;
use App\Repositories\JobRepo;
use App\Utils\GuzzleUtil;
use GuzzleHttp\Exception\ConnectException;
use Swoole\Coroutine;

final class SyncBetService
{
    public function __construct(private JobRepo $jobRepo)
    {
        // instantiate db
    }

    /**
     * @throws \MeekroDBException
     */
    public function fireOrQueue(
        int $mid,
        string $site,
        string $module,
        int $cronId = 0
    ): array
    {
        $maxCo = (int)env('MAX_CONCURRENCY');
        $running = Coroutine::stats()['coroutine_num'] ?? 0;
        $payload = [
            'module'          => '/betHistory/' . $module,
            'access_id'       => 'sync_bet',
            'access_token'    => 'sync_bet',
            'non_transaction' => 1,
            'mid'             => $mid,
            'site'            => $site,
        ];

        $job = $this->jobRepo->createQueueJob(
            $payload,
            $cronId,
            QueueJobStatusEnum::IN_QUEUE
        );

        if ($running < ($maxCo * 0.9)) {
            $job['status'] = QueueJobStatusEnum::IN_FLIGHT;
            Coroutine::create(function () use ($job) {
                $jobRepo = new JobRepo();

                try {
                    $job['status'] = $this->selfApi($job);
                } catch (\Throwable $e) {
                    // @TODO: How do we handle retries - put back and fire into maybe this coroutine again?

                    $job['status'] = QueueJobStatusEnum::FAILED;
                }

                $id = (int) $job['id'];
                $jobRepo->updateQueueJob($id, [
                    'status' => $job['status'],
                    'attempts' => $job['attempts'] + 1
                ]);

                // @TODO: Update the cronjob - MOVE back to PENDING for reprocessing
            });
        }

        return [
            'job' => $job,
            'decrypt' => [
                'job.payload' => $this->jobRepo::decompressPayload($job['payload'])
            ]
        ];
    }

    private function selfApi(array $job) : string
    {
        $target_url = env('WALLET_URL', 'http://localhost:8080') . '/api/v1/index.php';
        $http = new GuzzleUtil();
        $payload = json_decode($job['payload'], true) ?? [];

        try {
            $response = $http->execute('POST',
                $target_url,
                $payload,
                [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ]
            );

            // Wallet nile response validations
            if ($response['status'] <= 200 || $response['status'] > 299) {
                throw new \RuntimeException(
                    'Invalid response from SelfApi: ' . $response['body']
                );
            }

            return QueueJobStatusEnum::COMPLETED;
        } catch (ConnectException $e) {
            return QueueJobStatusEnum::TIMED_OUT;
        }
    }
}