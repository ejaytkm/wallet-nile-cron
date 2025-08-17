<?php
declare(strict_types=1);

namespace App\Utils;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\StreamHandler;

final class GuzzleUtil
{
    private Client $client;
    private bool $verify;
    private float $timeout;
    private float $connectTimeout;

    public function __construct(
        float $timeout = 30,
        float $connectTimeout = 20,
        ?Client $client = null,
    )
    {
        $handler = new StreamHandler();
        $this->verify = false;
        $this->timeout = $timeout ?? 30;
        $this->connectTimeout = $connectTimeout ?? 15;
        $this->client = $client ?? new Client([
            'handler'         => $handler,
            'verify'          => $this->verify,
            'timeout'         => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'http_errors'     => false,
        ]);
    }

    /**
     * Please handle exceptions yourself, this method will throw exceptions on HTTP errors.
     */
    public function execute(
        string $method,
        string $url,
        array $headers = [],
        array|string $data = [],
        ?string $bodyType = null
    ): array {
        $method  = strtoupper($method);
        if (!in_array($method, ['GET','POST','PUT','PATCH','DELETE','HEAD','OPTIONS'], true)) {
            throw new \InvalidArgumentException('Invalid method');
        }

        $headers = array_change_key_case($headers);
        $type = $headers['content-type'] ?? '';
        $opts = [];

        if ($bodyType) {
            switch ($bodyType) {
                case 'query':
                    $opts['query'] = is_array($data) ? $data : [];
                    break;
                case 'json':
                    $opts['json']  = is_array($data) ? $data : (string)$data;
                    break;
                case 'form':
                    $opts['form_params'] = is_array($data) ? $data : [];
                    break;
                case 'multipart':
                    $opts['multipart'] = $this->toMultipart($data);
                    break;
                case 'raw':
                    $opts['body']  = is_string($data) ? $data : json_encode($data);
                    break;
            }
        } else {
            if (in_array($method, ['GET','HEAD'], true)) {
                $opts['query'] = is_array($data) ? $data : [];
            } elseif (str_contains(strtolower($type), 'application/x-www-form-urlencoded')) {
                $opts['form_params'] = is_array($data) ? $data : [];
            } elseif (str_contains(strtolower($type), 'multipart/form-data')) {
                $opts['multipart'] = $this->toMultipart($data);
            } elseif (is_array($data)) {
                $opts['json'] = $data;
                $headers['content-type'] = $headers['content-type'] ?? 'application/json';
            } else {
                $opts['body'] = (string)$data;
            }
        }

        if (isset($opts['form_params']) || isset($opts['multipart'])) {
            unset($headers['content-type']);
        }

        $headers['accept'] = $headers['accept'] ?? 'application/json';
        $config = [
            'headers'      => $headers
        ] + $opts;

        $res = $this->client->{strtolower($method)}($url, $config);
        $status = $res->getStatusCode();

        if ($status < 200 || $status > 299) {
            throw new \RuntimeException(
                'HTTP Error: '.$status.' - '.$res->getReasonPhrase() . ' - URL: ' . $url . ' - Response: ' . $res->getBody()
            );
        }

        return [
            'status'  => $status,
            'body'    => (string)$res->getBody(),
            'headers' => $res->getHeaders(),
            'url'     => $url,
            'sent'    => $opts, // handy to inspect in logs
        ];
    }
    private function toMultipart(array|string $data): array
    {
        if (!is_array($data)) return [['name' => 'payload', 'contents' => (string)$data]];
        $parts = [];
        foreach ($data as $name => $value) {
            if (is_array($value) && array_key_exists('contents', $value)) {
                // already a Guzzle-style part
                $parts[] = ['name' => (string)$name] + $value;
            } else {
                $parts[] = ['name' => (string)$name, 'contents' => is_scalar($value) ? (string)$value : json_encode($value)];
            }
        }
        return $parts;
    }
}