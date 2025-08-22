<?php

use App\Utils\GuzzleUtil;
function env(string $key, $default = null)
{
    return $_ENV[$key] ?? $default;
}

function selfWalletNileApi($path, array $payload): array
{
    $http = new GuzzleUtil();
    $headers = [
        'Content-Type' => 'application/json',
        'Accept'       => 'application/json'
    ];
    $proxyPayload = [
        'fire_and_forget' => true,
        'method'          => 'POST',
        'headers'         => [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept'       => 'application/json',
        ],
        'payload'         => [
            ...$payload,
        ]
    ];

    if (!empty($payload['merchantId'])) {
        $proxyUrl = getMerchantServerConfig($payload['merchantId'], 'APIURL');
        $proxyPayload['url'] = $proxyUrl;
    }

    $url = 'http://' .  env('WALLET_NILE_URL') . $path;
    return $http->execute('POST', $url, $headers, $proxyPayload);
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
    }

    if ($attr === 'APIDIR') {
        return 'http://' . $apiEndpoint . '/api/v1';
    } else if ($attr === 'APIURL') {
        return 'http://' . $apiEndpoint . '/api/v1/index.php';
    } else if ($attr === 'CRONDIR') {
        return 'http://' . $cronEndpoint . '/api/v1';
    } else if ($attr === 'CRONURL') {
        return 'http://' . $cronEndpoint . '/api/v1/index.php';
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

function postAndForget($url,$data = []) {
    $tmp = [];
    foreach ($data as $k => $v) {
        $tmp[] = $k.'='.urlencode($v);
    }
    $parts = parse_url($url);
    if (!empty($parts['query'])) {
        $tmp[] = $parts['query'];
    }
    $post_string = implode('&',$tmp);
    if ($parts['scheme'] === 'https') {
        $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $fp = stream_socket_client('ssl://'.$parts['host'].':443', $errNo, $errStr, 5, STREAM_CLIENT_CONNECT, $context);
    } else {
        $fp = fsockopen($parts['host'], (empty($parts['port']) ? 80 : $parts['port']), $errNo, $errStr, 5);
    }
    if (!$fp) {
        return false;
    } else {
        $out = "POST ".$parts['path']." HTTP/1.1\r\n";
        $out.= "Host: ".$parts['host']."\r\n";
        $out.= "Content-Type: application/x-www-form-urlencoded\r\n";
        $out.= "Content-Length: ".strlen($post_string)."\r\n";
        $out.= "Connection: Close\r\n\r\n";
        if (isset($post_string)) $out.= $post_string;
        fwrite($fp, $out);
        stream_set_timeout($fp, 0, 10000);
        fread($fp, 1);
        fclose($fp);
        return true;
    }
}