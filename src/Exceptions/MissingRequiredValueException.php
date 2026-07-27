<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Exceptions;

use RuntimeException;

final class MissingRequiredValueException extends RuntimeException
{
    public static function for(string $variable, string $commandKey): self
    {
        return new self(sprintf(
            'Command [%s] requires a value for variable "%s".',
            $commandKey,
            $variable,
        ));
    }
}
