<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Bootstrap.php';

use App\Repositories\JobRepo;
use App\Repositories\MerchantRepo;

$merchantRp = new MerchantRepo();
$jobsRp = new JobRepo();
