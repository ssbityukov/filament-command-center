<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Execution;

use Bityukov\CommandCenter\Definitions\CommandDefinition;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Per command and user, plus an optional global limit.
 *
 * A null user id is one shared bucket rather than an exemption: an
 * unauthenticated caller is the one we least want to hand an unbounded number
 * of privileged runs.
 */
final class RunRateLimiter
{
    /**
     * @return int|null seconds until the next attempt, or null when allowed
     */
    public function check(CommandDefinition $definition, int|string|null $userId): ?int
    {
        $user = $userId === null ? 'guest' : (string) $userId;

        if ($definition->rateLimit !== null) {
            $blocked = $this->consume(
                'cc:rl:'.$definition->key.':'.$user,
                $definition->rateLimit['attempts'],
                $definition->rateLimit['minutes'],
            );

            if ($blocked !== null) {
                return $blocked;
            }
        }

        $global = config('command-center.rate_limit.global');

        if (is_array($global) && isset($global['attempts'], $global['minutes'])) {
            return $this->consume(
                'cc:rl:global:'.$user,
                (int) $global['attempts'],
                (int) $global['minutes'],
            );
        }

        return null;
    }

    private function consume(string $key, int $attempts, int $minutes): ?int
    {
        if (RateLimiter::tooManyAttempts($key, $attempts)) {
            return RateLimiter::availableIn($key);
        }

        RateLimiter::hit($key, $minutes * 60);

        return null;
    }
}
