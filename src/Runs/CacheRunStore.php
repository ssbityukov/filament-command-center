<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Runs;

use Illuminate\Contracts\Cache\Repository;

/**
 * Zero-migration history driver.
 *
 * One key holds an ordered index of run ids, newest first; each run lives under
 * its own key so reading one run does not deserialise the whole history. Both
 * expire, which is the trade-off documented in the README: a cache flush clears
 * the audit trail, and installs that need durability opt into the database
 * driver instead.
 */
final class CacheRunStore implements RunStore
{
    private const INDEX_KEY = 'cc:runs:index';

    public function __construct(
        private readonly Repository $cache,
        private readonly int $max,
        private readonly int $ttlHours,
    ) {}

    public function put(Run $run): void
    {
        $index = $this->index();

        $index = array_values(array_filter($index, fn (string $id): bool => $id !== $run->id));
        array_unshift($index, $run->id);

        foreach (array_slice($index, $this->max) as $expired) {
            $this->cache->forget($this->runKey($expired));
        }

        $index = array_slice($index, 0, $this->max);

        $this->cache->put($this->runKey($run->id), $run->toArray(), $this->ttl());
        $this->cache->put(self::INDEX_KEY, $index, $this->ttl());
    }

    public function find(string $id): ?Run
    {
        $data = $this->cache->get($this->runKey($id));

        return is_array($data) ? Run::fromArray($data) : null;
    }

    /**
     * @return array<int, Run>
     */
    public function recent(int $limit = 100): array
    {
        $runs = [];

        foreach ($this->index() as $id) {
            if (count($runs) >= $limit) {
                break;
            }

            // An index entry can outlive its run key when a store evicts under
            // memory pressure. A missing run is skipped rather than faked.
            $run = $this->find($id);

            if ($run !== null) {
                $runs[] = $run;
            }
        }

        return $runs;
    }

    public function forget(string $id): void
    {
        $this->cache->forget($this->runKey($id));

        $this->cache->put(
            self::INDEX_KEY,
            array_values(array_filter($this->index(), fn (string $indexed): bool => $indexed !== $id)),
            $this->ttl(),
        );
    }

    public function flush(): void
    {
        foreach ($this->index() as $id) {
            $this->cache->forget($this->runKey($id));
        }

        $this->cache->forget(self::INDEX_KEY);
    }

    /**
     * @return array<int, string>
     */
    private function index(): array
    {
        $index = $this->cache->get(self::INDEX_KEY, []);

        return is_array($index) ? array_values(array_filter($index, 'is_string')) : [];
    }

    private function runKey(string $id): string
    {
        return 'cc:run:'.$id;
    }

    private function ttl(): int
    {
        return $this->ttlHours * 3600;
    }
}
