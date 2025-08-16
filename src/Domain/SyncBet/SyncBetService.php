<?php
declare(strict_types=1);

namespace App\Domain\SyncBet;

use App\Repositories\Enum\QueueJobStatusEnum;
use App\Repositories\JobRepo;
use App\Utils\GuzzleUtil;
use Carbon\Carbon;
use GuzzleHttp\Exception\ConnectException;
use PDO;
use Swoole\Coroutine;

final class SyncBetService
{
    public function __construct(private JobRepo $jobRepo)
    {
    }

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
            'module'          => '/betHistory/jili' . $module,
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
                $conn = new PDO(
                    (string) env('DB_DSN'),
                    (string) env('DB_USER'),
                    (string) env('DB_PASS')
                );

                $jobRepo = new JobRepo($conn);

                try {
                    $job['status'] = $this->selfApi($job);
                } catch (\Throwable $e) {
                    // @TODO: How do we handle retries?
                    $job['status'] = QueueJobStatusEnum::FAILED;
                    echo "Error: " . $e->getMessage() . "\n";
                }

                $jobRepo->updateQueueJob($job['id'], [
                    'status' => $job['status'],
                    'attempt' => $job['attempt'] + 1,
                    'completed_at' => Carbon::now()->toDateTimeString()
                ]);
            });
        }

        return [
            'id'  => $job['id'],
            'status'  => $job['status'],
            'payload' => $payload['input']
        ];
    }

    private function selfApi(array $job) : string
    {
        $target_url = env('WALLET_URL', 'http://localhost:8080') . '/api/v1/index.php';
        $http = new GuzzleUtil();
        $payload = $job['payload']['input'] ?? [];

        try {
            $response = $http->execute(
                $target_url,
                'POST',
                $payload,
                ['Content-Type' => 'application/x-www-form-urlencoded']
            );

            echo "Response: " . json_encode($response) . "\n";
            return QueueJobStatusEnum::COMPLETED;
        } catch (ConnectException $e) {
            return QueueJobStatusEnum::TIMED_OUT;
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Error while calling SelfApi: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}