<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Execution;

use Illuminate\Contracts\Cache\Repository;

/**
 * Live output for one run, held in cache.
 *
 * This is deliberately not a RunStore concern: a chatty command produces
 * hundreds of chunks a second, and writing each one to a database would be
 * pathological. The finished output is copied onto the Run record when the run
 * ends, so the durable record never depends on this buffer surviving.
 */
final class OutputBuffer
{
    public function __construct(private readonly Repository $cache) {}

    public function append(string $runId, string $chunk): void
    {
        $this->cache->put(
            $this->key($runId),
            $this->cap($this->all($runId).$chunk),
            $this->ttl(),
        );
    }

    public function read(string $runId, int $offset = 0): string
    {
        return substr($this->all($runId), max($offset, 0)) ?: '';
    }

    public function all(string $runId): string
    {
        $value = $this->cache->get($this->key($runId), '');

        return is_string($value) ? $value : '';
    }

    public function length(string $runId): int
    {
        return strlen($this->all($runId));
    }

    public function forget(string $runId): void
    {
        $this->cache->forget($this->key($runId));
    }

    /**
     * Keep the head and the tail.
     *
     * The opening lines say what the command decided to do and the closing
     * lines say how it ended. The middle of a runaway log is the part nobody
     * reads, so that is what goes.
     */
    private function cap(string $output): string
    {
        $max = max((int) config('command-center.output.max_bytes', 262144), 64);

        if (strlen($output) <= $max) {
            return $output;
        }

        $half = intdiv($max, 2);

        return substr($output, 0, $half)
            ."\n… output truncated …\n"
            .substr($output, -$half);
    }

    private function key(string $runId): string
    {
        return 'cc:run:'.$runId.':out';
    }

    private function ttl(): int
    {
        return max((int) config('command-center.output.ttl_minutes', 60), 1) * 60;
    }
}
