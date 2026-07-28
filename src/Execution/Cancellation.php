<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Execution;

use Illuminate\Contracts\Cache\Repository;

/**
 * A cancellation is a request, not an order.
 *
 * The flag is written by whoever pressed Cancel and read by whichever process
 * is actually running the command — a web request for a sync run, a worker for
 * a queued one. Neither can reach into the other, and cache is the only thing
 * both can see.
 */
final class Cancellation
{
    public function __construct(private readonly Repository $cache) {}

    public function request(string $runId): void
    {
        $this->cache->put($this->key($runId), true, $this->ttl());
    }

    public function requested(string $runId): bool
    {
        return (bool) $this->cache->get($this->key($runId), false);
    }

    public function forget(string $runId): void
    {
        $this->cache->forget($this->key($runId));
    }

    private function key(string $runId): string
    {
        return 'cc:run:'.$runId.':cancel';
    }

    private function ttl(): int
    {
        return max((int) config('command-center.output.ttl_minutes', 60), 1) * 60;
    }
}
