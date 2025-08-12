<?php
declare(strict_types=1);

namespace App\Utils;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\StreamHandler;

final class GuzzleUtil
{
    private Client $client;
    private bool $verify;
    private float $timeout;
    private float $connectTimeout;
    private array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

    public function __construct(
        ?Client $client = null,
        ?float $timeout = 60,
        ?float $connectTimeout = 30
    )
    {
        // Prefer StreamHandler to avoid Swoole's native cURL hook and OpenSSL compile issues
        $handler = new StreamHandler();

        $this->verify = false;
        $this->timeout = $timeout ?? (float)(env('TARGET_TIMEOUT', 60));
        $this->connectTimeout = $connectTimeout ?? min($this->timeout, 60);

        $this->client = $client ?? new Client([
            'handler'         => $handler,
            'verify'          => $this->verify,
            'timeout'         => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'http_errors'     => false, // don't throw on 4xx/5xx
        ]);
    }


    public function execute(string $method, string $url, array $headers = [], array $data = []): mixed
    {
        if (!\in_array(\strtoupper($method), $this->methods)) {
            throw new \InvalidArgumentException('Invalid method provided using CurlApi');
        }

        $config = [
            'headers' => empty($headers) ? [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ] : $headers,
            'json' => $data,
            'form_params' => $data,
        ];
        $this->createDataBasedOnConfig($config, $data);

        try {
            $response = $this->client->{\strtolower($method)}($url, $config);

            if (
                ($response->getStatusCode() < 200 ||
                    $response->getStatusCode() > 299)
            ) {
                throw new \Exception('Error non 2xx status code: ' . $response->getStatusCode());
            }

            return [
                'status'  => $response->getStatusCode(),
                'body'    => (string) $response->getBody(),
                'headers' => $response->getHeaders(),
                'url'     => $url,
            ];
        } catch (RequestException $e) {
            throw new \RuntimeException('RequestException ' . $e->getMessage(), 0, $e);
        } catch (ConnectException $e) {
            throw new \RuntimeException('ConnectException ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Throwable ' . $e->getMessage(), 0, $e);
        }
    }

    private function createDataBasedOnConfig(array &$config, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $contentType = $this->getContentType($config['headers']);

        if ($contentType === 'application/x-www-form-urlencoded') {
            $config['form_params'] = $data;
            unset($config['json']);
        } else {
            $config['json'] = $data;
            unset($config['form_params']);
        }
    }

    private function getContentType(array $headers): string
    {
        return $headers['Content-Type'] ?? 'application/json';
    }
}