<?php
declare(strict_types=1);

namespace App\Utils;

use Predis\Client;

class RedisUtil
{
    private Client $client;

    public function __construct($client = null)
    {
        if ($client instanceof Client) {
            $this->client = $client;
        } else {
            $this->client = new Client([
                'scheme' => 'tcp',
                'host' => env('REDIS_HOST', '127.0.0.1'),
                'port' => (int) env('REDIS_PORT', 6379),
                'password' => env('REDIS_PASSWORD', null),
                'database' => (int) env('REDIS_DB', 0),
            ]);
        }
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function get(string $key)
    {
        $ret = $this->client->get($key);
        if (!empty($ret)) {
            $tmp = json_decode($ret,true);
            if (!is_null($tmp)) {
                $ret = $tmp;
            }
        }
        return $ret;
    }

    public function set(string $key, $value, int $ttl = 0): void
    {
        if (!is_scalar($value)) {
            $value = json_encode($value);
        }

        $this->client->set($key, $value);
        if ($ttl > 0) {
            $this->client->expire($key, $ttl);
        }
    }

    public function delete(string $key): void
    {
        $this->client->del($key);
    }
}