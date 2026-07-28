<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Exceptions;

use RuntimeException;

final class UnknownModelValueException extends RuntimeException
{
    public static function for(string $variable, string $value): self
    {
        return new self(sprintf(
            'Variable "%s" rejected the value "%s" because no record with that value exists within '
            .'the variable\'s own query. A scoped select cannot be used to reach a record outside '
            .'its scope.',
            $variable,
            $value,
        ));
    }

    public static function missingModel(string $variable): self
    {
        return new self(sprintf(
            'Variable "%s" is a model variable with no model class. Call ->model(SomeModel::class).',
            $variable,
        ));
    }
}
