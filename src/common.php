<?php

use App\Utils\GuzzleUtil;
function env(string $key, $default = null)
{
    return $_ENV[$key] ?? $default;
}

function selfWalletNileApi(
    $path,
    array $payload,
    $walletEnv = null,
    $compression = false
): array
{
    $http = new GuzzleUtil();
    $headers = [
        'Content-Type' => 'application/json',
        'Accept'       => 'application/json',
    ];
    $data = [
        'walletEnv'      => $walletEnv, // don't delete this ~ logging. see gates
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

    if ($compression) {
        $headers['Content-Encoding'] = 'gzip';
        $data = ["content" => compressData($data)];
    }

    try {
        return $http->execute(
            'POST',
            getNileUrl($path),
            $headers,
            $data
        );
    } catch (Throwable $e) {
        return $http->execute(
            'POST',
            getNileUrl($path, 1),
            $headers,
            $data
        );
    }
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

    if (!in_array(strtolower(env('APP_ENV')), ['production', 'prod'])) {
        $apiEndpoint = env('WALLET_URL', 'server.wallet.xdev');
        $cronEndpoint = env('WALLET_URL', 'server.wallet.xdev');
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

function postAndForget($url, $data = []): bool
{
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

function compressData($data): string
{
    $lvl = 6;
    $compressed = gzcompress(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $lvl);
    return base64_encode($compressed);
}

function getNileUrl($path, $nileEnv = 0): string
{
    $baseUrl = 'http://' . env('WALLET_NILE_URL');

    // Force on GATES
    if (in_array(strtolower(env('APP_ENV')), ['production', 'prod'])) {
        $baseUrl = $nileEnv === 0
            ? 'http://wallet-nile-gate.eba-gcmxump7.ap-southeast-1.elasticbeanstalk.com'
            : 'http://wallet-nile-gate-2.eba-gcmxump7.ap-southeast-1.elasticbeanstalk.com';
    }

    return $baseUrl . $path;
}