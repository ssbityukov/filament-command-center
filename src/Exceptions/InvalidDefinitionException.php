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

    public static function tokenInCommandPosition(string $key): self
    {
        return new self(sprintf(
            'Command [%s] puts a token in the first element of its "run" template. The first '
            .'element names the binary or the Artisan command being run, so a token there would '
            .'let a submitted value choose what executes, defeating the allow-list. Make the '
            .'first element a literal.',
            $key,
        ));
    }

    public static function unknownCommandType(string $key, string $type): self
    {
        return new self(sprintf(
            'Command [%s] has unknown type "%s". Expected one of: artisan, shell.',
            $key,
            $type,
        ));
    }

    public static function invalidValue(string $key, string $field, string $expected, string $actual): self
    {
        return new self(sprintf(
            'Command [%s] has an invalid "%s" value: expected %s, got %s.',
            $key,
            $field,
            $expected,
            $actual,
        ));
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
