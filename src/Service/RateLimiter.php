<?php
namespace App\Service;

use Predis\Client;

class RateLimiter
{
private Client $redis;
private int $limit;
private int $windowSeconds;

    public function __construct(Client $redis, int $limit = 10, int $windowSeconds = 60)
    {
        $this->redis = $redis;
        $this->limit = $limit;
        $this->windowSeconds = $windowSeconds;
    }

    // returns [bool allowed, int remaining]
    public function allow(string $key): array
    {
        $redisKey = "rate:{$key}";
        $now = time();
        $tx = $this->redis->multi();
        $tx->incr($redisKey);
        $tx->ttl($redisKey);
        $res = $tx->exec();
        $count = $res[0];
        $ttl = $res[1];

        if ($ttl === -1) {
            $this->redis->expire($redisKey, $this->windowSeconds);
            $ttl = $this->windowSeconds;
        } elseif ($ttl === -2) {
            $this->redis->expire($redisKey, $this->windowSeconds);
            $ttl = $this->windowSeconds;
        }

        return [$count <= $this->limit, max(0, $this->limit - $count)];
    }
}
