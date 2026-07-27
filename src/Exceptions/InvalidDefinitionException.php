<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Exceptions;

use RuntimeException;

final class InvalidDefinitionException extends RuntimeException
{
    public static function missingRun(string $key): self
    {
        return new self(sprintf('Command [%s] is missing the required "run" key.', $key));
    }

    public static function unknownVariableType(string $key, string $variable, string $type): self
    {
        return new self(sprintf(
            'Command [%s] variable "%s" has unknown type "%s". Expected one of: text, select, boolean, model.',
            $key,
            $variable,
            $type,
        ));
    }
}
