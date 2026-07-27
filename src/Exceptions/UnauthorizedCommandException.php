<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Exceptions;

use RuntimeException;

final class UnauthorizedCommandException extends RuntimeException
{
    public static function for(string $key): self
    {
        return new self(sprintf('You are not authorized to run the command [%s].', $key));
    }
}
