<?php
// RPS = 200
// called every 10 seconds
declare(strict_types=1);

$startTime = microtime(true);
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/bootstrap.php';

$jobsRp = new App\Repositories\JobRepo();
$jobsdb = $jobsRp->getDB();
$total_fired = 0;

$qJobs = $jobsdb->query("SELECT id FROM queue_jobs WHERE status = 'IN_QUEUE'  LIMIT 200");

// Fire the jobs that are still in queue
foreach ($qJobs as $c) {
    try {
        selfWorkerApi('/requeue/syncbethistory', [
            'jobId' => (int) $c['id']
        ]);
        $total_fired++;
    } catch (Exception $e) {
        echo "Error firing job ID {$c['id']}: " . $e->getMessage() . "\n";
    }
}

$file = __FILE__;
echo "Executed script: $file\n";
echo "TotalFired:" . $total_fired  .
     "|ExecTime:" . (microtime(true) - $startTime) . "s\n";
