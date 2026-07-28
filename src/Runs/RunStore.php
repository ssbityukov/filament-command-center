<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Runs;

interface RunStore
{
    public function put(Run $run): void;

    public function find(string $id): ?Run;

    /**
     * Runs newest first.
     *
     * @return array<int, Run>
     */
    public function recent(int $limit = 100): array;

    public function forget(string $id): void;

    public function flush(): void;
}
