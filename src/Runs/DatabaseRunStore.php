<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Runs;

use RuntimeException;

/**
 * Placeholder. Task 2 implements this; the contract dataset does not include
 * the database driver until it does.
 */
final class DatabaseRunStore implements RunStore
{
    public function put(Run $run): void
    {
        throw new RuntimeException('Not implemented yet.');
    }

    public function find(string $id): ?Run
    {
        throw new RuntimeException('Not implemented yet.');
    }

    /**
     * @return array<int, Run>
     */
    public function recent(int $limit = 100): array
    {
        throw new RuntimeException('Not implemented yet.');
    }

    public function forget(string $id): void
    {
        throw new RuntimeException('Not implemented yet.');
    }

    public function flush(): void
    {
        throw new RuntimeException('Not implemented yet.');
    }
}
