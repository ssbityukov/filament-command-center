<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Sources;

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Definitions\CommandDefinition;
use Bityukov\CommandCenter\Definitions\Flag;
use Bityukov\CommandCenter\Definitions\Variables\BooleanVariable;
use Bityukov\CommandCenter\Definitions\Variables\ModelVariable;
use Bityukov\CommandCenter\Definitions\Variables\SelectVariable;
use Bityukov\CommandCenter\Definitions\Variables\TextVariable;
use Bityukov\CommandCenter\Definitions\Variables\Variable;
use Bityukov\CommandCenter\Exceptions\InvalidDefinitionException;

final class ArrayDefinitionParser
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function parse(string $key, array $config, int $defaultTimeout): CommandDefinition
    {
        if (! isset($config['run']) || ! is_string($config['run']) || $config['run'] === '') {
            throw InvalidDefinitionException::missingRun($key);
        }

        $command = Command::make($key)->run($config['run']);

        if (isset($config['label'])) {
            $command->label($config['label']);
        }

        if (($config['type'] ?? 'artisan') === 'shell') {
            $command->shell();
        }

        $command
            ->group($config['group'] ?? null)
            ->help($config['help'] ?? null)
            ->queue($config['queue'] ?? false)
            ->ability($config['ability'] ?? null)
            ->concurrency($config['concurrency'] ?? null)
            ->confirm($config['confirm'] ?? false)
            ->progress((bool) ($config['progress'] ?? false));

        if (isset($config['timeout'])) {
            $command->timeout((int) $config['timeout']);
        }

        if (isset($config['rate_limit'])) {
            $command->rateLimit(
                (int) $config['rate_limit']['attempts'],
                (int) $config['rate_limit']['minutes'],
            );
        }

        $command->variables($this->parseVariables($key, $config['variables'] ?? []));
        $command->flags($this->parseFlags($config['flags'] ?? []));

        return $command->toDefinition($defaultTimeout);
    }

    /**
     * @param  array<string, array<string, mixed>>  $variables
     * @return array<int, Variable>
     */
    private function parseVariables(string $key, array $variables): array
    {
        $parsed = [];

        foreach ($variables as $name => $config) {
            $parsed[] = $this->parseVariable($key, (string) $name, $config);
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function parseVariable(string $key, string $name, array $config): Variable
    {
        $variable = match ($config['type'] ?? 'text') {
            'text' => TextVariable::make($name),
            'select' => SelectVariable::make($name)->options($config['options'] ?? []),
            'boolean' => BooleanVariable::make($name)->trueValue((string) ($config['true_value'] ?? '1')),
            'model' => ModelVariable::make($name)
                ->model($config['model'] ?? '')
                ->titleAttribute($config['title_attribute'] ?? 'name')
                ->valueAttribute($config['value_attribute'] ?? 'id'),
            default => throw InvalidDefinitionException::unknownVariableType(
                $key,
                $name,
                (string) $config['type'],
            ),
        };

        if (isset($config['label'])) {
            $variable = $variable->label($config['label']);
        }

        if (isset($config['default'])) {
            $variable = $variable->default((string) $config['default']);
        }

        if (isset($config['help'])) {
            $variable = $variable->help($config['help']);
        }

        return $variable
            ->required((bool) ($config['required'] ?? false))
            ->redact((bool) ($config['redact'] ?? false))
            ->rules($config['rules'] ?? []);
    }

    /**
     * @param  array<string, array<string, mixed>>  $flags
     * @return array<int, Flag>
     */
    private function parseFlags(array $flags): array
    {
        $parsed = [];

        foreach ($flags as $name => $config) {
            $flag = Flag::make((string) $name)
                ->default((bool) ($config['default'] ?? false))
                ->help($config['help'] ?? null);

            $parsed[] = isset($config['label']) ? $flag->label($config['label']) : $flag;
        }

        return $parsed;
    }
}
