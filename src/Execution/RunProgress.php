<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Execution;

use Illuminate\Contracts\Cache\Repository;

final class RunProgress
{
    public function __construct(private readonly Repository $cache) {}

    public function set(string $runId, ?int $percent): void
    {
        if ($percent === null) {
            $this->forget($runId);

            return;
        }

        $this->cache->put($this->key($runId), max(0, min($percent, 100)), $this->ttl());
    }

    public function get(string $runId): ?int
    {
        $value = $this->cache->get($this->key($runId));

        return is_int($value) ? $value : null;
    }

    public function forget(string $runId): void
    {
        $this->cache->forget($this->key($runId));
    }

    private function key(string $runId): string
    {
        return 'cc:run:'.$runId.':progress';
    }

    private function ttl(): int
    {
        return max((int) config('command-center.output.ttl_minutes', 60), 1) * 60;
    }
}
