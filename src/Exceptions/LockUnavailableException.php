<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Exceptions;

use RuntimeException;

final class LockUnavailableException extends RuntimeException
{
    public static function for(string $key, string $store): self
    {
        return new self(sprintf(
            'Command [%s] sets a concurrency limit, but the configured cache store [%s] does not '
            .'support atomic locks, so the limit cannot be enforced. Use a store that does '
            .'(array, file, database, memcached, redis or dynamodb) or remove the limit.',
            $key,
            $store,
        ));
    }
}
