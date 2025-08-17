<?php

use App\Utils\GuzzleUtil;

if (!defined('SWOOLE_HOOK_ALL')) define('SWOOLE_HOOK_ALL', 0xFFFFFF);
if (!defined('SWOOLE_HOOK_NATIVE_CURL')) define('SWOOLE_HOOK_NATIVE_CURL', 0x2000);

function env(string $key, $default = null)
{
    return $_ENV[$key] ?? $default;
}

function selfWalletApi(array $payload): array
{
    if (empty($payload['merchantId'])) {
        throw new \InvalidArgumentException('Merchant ID (mid) is required in the payload.');
    }

    $http = new GuzzleUtil(60, 60);
    $url = getMerchantServerConfig($payload['merchantId'], 'APIURL');

    $headers = [
        'Content-Type' => 'application/x-www-form-urlencoded',
        'Accept'       => 'application/json'
    ];

    return $http->execute('POST', $url, $headers, $payload);
}

function selfWorkerApi(string $path, array $payload): array
{
    $http = new GuzzleUtil();
    $url = 'http://' . env('APP_WORKER_LB_URL', 'http://localhost:9501') . $path;
    $headers = [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json'
    ];

    return $http->execute('POST', $url, $headers, $payload);
}

function getMerchantServerConfig($merchantId, $attr): string
{
    if (isGWallet($merchantId)) {
        $apiEndpoint = 'gbox-env-2.ap-southeast-1.elasticbeanstalk.com';
        $cronEndpoint = 'gbox-env-cron.ap-southeast-1.elasticbeanstalk.com';
        $serverIP = '54.179.39.20';
    } else {
        $apiEndpoint = 'wallet-merchant-env-2.eba-abam5mc5.ap-southeast-1.elasticbeanstalk.com';
        $cronEndpoint = 'wallet-merchant-env-cron.eba-abam5mc5.ap-southeast-1.elasticbeanstalk.com';
        $serverIP = '54.169.192.26';
    }

    if (
        env('APP_ENV') != 'production' ||
        env('APP_ENV') != 'prod'
    ) {
        $apiEndpoint = env('WALLET_URL', 'server.wallet.xdev');
        $cronEndpoint = env('APP_WORKER_LB_URL', 'secure.wallet-nile-cron.xdev');
        $serverIP = '';
    }

    if ($attr === 'APIDIR') {
        return 'http://'.$apiEndpoint.'/api/v1';
    } else if ($attr === 'APIURL') {
        return 'http://'.$apiEndpoint.'/api/v1/index.php';
    } else if ($attr === 'CRONDIR') {
        return 'http://'.$cronEndpoint.'/api/v1';
    } else if ($attr === 'CRONURL') {
        return 'http://'.$cronEndpoint.'/api/v1/index.php';
    } else if ($attr === 'SERVERIP') {
        return $serverIP;
    }

    return '';
}

function isGWallet($mId): bool
{
    if ($mId > 1000 && $mId < 10000) {
        return true;
    }
    return false;
}

function getAppRoot(): string
{
    global $appRoot;

    if (!isset($appRoot)) {
        return realpath(__DIR__ . '/..');
    }

    throw new \RuntimeException('App root not set. Please define $appRoot in your script.');
}