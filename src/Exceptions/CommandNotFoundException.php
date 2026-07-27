<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Exceptions;

use RuntimeException;

final class CommandNotFoundException extends RuntimeException
{
    public static function for(string $key): self
    {
        return new self(sprintf('No command is registered with the key [%s].', $key));
    }
}
