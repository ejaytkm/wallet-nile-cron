<?php

$minute = date('i');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/bootstrap.php';

$apiDir = getMerchantServerConfig(0,'APIDIR');
$cronDir = getMerchantServerConfig(0,'CRONDIR');
$self = "http://" . env('APP_URL');

postAndForget($self . '/crond/sync_bet_history.php');