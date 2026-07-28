<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Execution;

use Bityukov\CommandCenter\Definitions\CommandDefinition;
use Bityukov\CommandCenter\Exceptions\MissingRequiredValueException;
use Bityukov\CommandCenter\Exceptions\UnknownTokenException;
use Bityukov\CommandCenter\Exceptions\UnsafeValueException;

final class ArgvBuilder
{
    /**
     * Build the argument vector for a command.
     *
     * Values are never escaped or interpolated into a command string; each one
     * becomes a discrete argv element. Escaping, where a platform needs it, is
     * Symfony Process's job — adding it here would double-escape.
     *
     * @param  array<string, mixed>  $input
     * @return array<int, string>
     */
    public function build(CommandDefinition $definition, array $input): array
    {
        $argv = [];

        foreach ($this->elements($definition->run) as $element) {
            $resolved = $this->resolveElement($definition, $element, $input);

            if ($resolved !== null) {
                $argv[] = $resolved;
            }
        }

        foreach ($definition->flags as $flag) {
            $enabled = array_key_exists($flag->name, $input)
                ? filter_var($input[$flag->name], FILTER_VALIDATE_BOOLEAN)
                : $flag->default;

            if ($enabled) {
                $argv[] = $flag->name;
            }
        }

        return $argv;
    }

    /**
     * @return array<int, string>
     */
    private function elements(string $template): array
    {
        return preg_split('/\s+/', trim($template), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveElement(CommandDefinition $definition, string $element, array $input): ?string
    {
        preg_match_all('/\{(\w+)\}/', $element, $matches, PREG_OFFSET_CAPTURE);

        $tokens = array_column($matches[1], 0);

        if ($tokens === []) {
            return $element;
        }

        // A token at the very start of an element decides that element's first
        // character, so a value beginning with "-" makes the whole element an
        // option of the target command. A token with literal text before it
        // cannot: that text already fixed the element's meaning.
        $leadingToken = $matches[0][0][1] === 0 ? $tokens[0] : null;

        $replacements = [];

        foreach ($tokens as $token) {
            $variable = $definition->variable($token);

            if ($variable === null) {
                throw UnknownTokenException::for($token, $definition->key);
            }

            $value = $variable->resolve($input[$token] ?? null);

            if ($value === null) {
                if ($variable->required) {
                    throw MissingRequiredValueException::for($token, $definition->key);
                }

                return null;
            }

            if ($token === $leadingToken && ! $variable->allowsLeadingDash && str_starts_with($value, '-')) {
                throw UnsafeValueException::leadingDash($definition->key, $token);
            }

            $replacements['{'.$token.'}'] = $value;
        }

        return strtr($element, $replacements);
    }
}
