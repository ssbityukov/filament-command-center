<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Exceptions;

use RuntimeException;

final class UnknownTokenException extends RuntimeException
{
    public static function for(string $token, string $commandKey): self
    {
        return new self(sprintf(
            'Command [%s] uses token {%s} but no variable named "%s" is defined.',
            $commandKey,
            $token,
            $token,
        ));
    }
}
