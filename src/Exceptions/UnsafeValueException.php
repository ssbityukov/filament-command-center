<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Exceptions;

use RuntimeException;

/**
 * A submitted value was rejected because it would stop being a plain value.
 *
 * This is not about shell metacharacters — those are harmless, because values
 * never become part of a command string. It is about a value changing its own
 * grammatical role in the argument vector: becoming an option instead of an
 * operand, or falling outside the set a variable declared it accepts.
 */
final class UnsafeValueException extends RuntimeException
{
    public static function leadingDash(string $key, string $variable): self
    {
        return new self(sprintf(
            'Command [%s] rejected the value for variable "%s" because it starts with "-". '
            .'The token {%s} opens its argv element, so the value decides that element\'s first '
            .'character and the target command would read it as an option rather than as a value. '
            .'Put literal text before the token '
            .'(for example --name={%s}), or call ->allowsLeadingDash() on the variable if the '
            .'command genuinely accepts an operand that starts with a dash.',
            $key,
            $variable,
            $variable,
            $variable,
        ));
    }

    /**
     * @param  array<int, string>  $allowed
     */
    public static function notAnOption(string $variable, string $value, array $allowed): self
    {
        return new self(sprintf(
            'Select variable "%s" rejected the value "%s" because it is not one of its options. '
            .'Allowed values: %s.',
            $variable,
            $value,
            implode(', ', $allowed),
        ));
    }
}
