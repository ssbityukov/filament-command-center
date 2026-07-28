<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Execution;

use Bityukov\CommandCenter\Definitions\CommandDefinition;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Str;

/**
 * At most N simultaneous runs of one command.
 *
 * Slots rather than a counter: a counter cannot be decremented safely by a
 * worker that died mid-run, whereas a slot lock simply expires. The TTL is the
 * command's own timeout plus a minute, so no run can hold a slot much past the
 * point where it must already have been killed.
 *
 * Release is owner-checked, so a late finisher cannot free somebody else's slot.
 */
final class ConcurrencyLock
{
    public function __construct(private readonly CacheFactory $cache) {}

    public function acquire(CommandDefinition $definition): ?string
    {
        if ($definition->concurrency === null) {
            return Str::uuid()->toString();
        }

        foreach (range(1, max($definition->concurrency, 1)) as $slot) {
            $token = Str::uuid()->toString();

            if ($this->lock($definition, $slot, $token)->get()) {
                return $slot.':'.$token;
            }
        }

        return null;
    }

    public function release(CommandDefinition $definition, string $owner): void
    {
        if ($definition->concurrency === null || ! str_contains($owner, ':')) {
            return;
        }

        [$slot, $token] = explode(':', $owner, 2);

        $this->lock($definition, (int) $slot, $token)->release();
    }

    private function lock(CommandDefinition $definition, int $slot, string $token): Lock
    {
        return $this->cache
            ->store(config('command-center.history.store'))
            ->lock('cc:conc:'.$definition->key.':'.$slot, $definition->timeout + 60, $token);
    }
}
