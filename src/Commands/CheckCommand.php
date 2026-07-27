<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Commands;

use Bityukov\CommandCenter\CommandRegistry;
use Bityukov\CommandCenter\Definitions\CommandDefinition;
use Bityukov\CommandCenter\Definitions\CommandType;
use Bityukov\CommandCenter\Definitions\Variables\ModelVariable;
use Bityukov\CommandCenter\Definitions\Variables\SelectVariable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;
use Throwable;

final class CheckCommand extends Command
{
    protected $signature = 'command-center:check';

    protected $description = 'Validate every Command Center definition and fail on configuration errors.';

    /** @var array<int, string> */
    private array $errors = [];

    /** @var array<int, string> */
    private array $warnings = [];

    public function handle(CommandRegistry $registry): int
    {
        try {
            $definitions = $registry->all();
        } catch (Throwable $exception) {
            $this->components->error('Could not build definitions: '.$exception->getMessage());

            return self::FAILURE;
        }

        foreach ($definitions as $definition) {
            $this->checkDefinition($definition);
        }

        foreach ($this->warnings as $warning) {
            $this->components->warn($warning);
        }

        foreach ($this->errors as $error) {
            $this->components->error($error);
        }

        $this->newLine();
        $this->line(sprintf(
            '%d command%s checked, %d error%s, %d warning%s.',
            count($definitions),
            count($definitions) === 1 ? '' : 's',
            count($this->errors),
            count($this->errors) === 1 ? '' : 's',
            count($this->warnings),
            count($this->warnings) === 1 ? '' : 's',
        ));

        return $this->errors === [] ? self::SUCCESS : self::FAILURE;
    }

    private function checkDefinition(CommandDefinition $definition): void
    {
        $key = $definition->key;

        if (trim($definition->run) === '') {
            $this->errors[] = "[{$key}] has an empty run template.";
        }

        $tokens = $definition->tokens();

        foreach ($tokens as $token) {
            if ($definition->variable($token) === null) {
                $this->errors[] = "[{$key}] uses token {{$token}} but no variable named \"{$token}\" is defined.";
            }
        }

        foreach ($definition->variables as $name => $variable) {
            if (! in_array($name, $tokens, true)) {
                $this->warnings[] = "[{$key}] defines variable \"{$name}\" that no token uses.";
            }

            if ($variable instanceof ModelVariable && ! class_exists($variable->model)) {
                $this->errors[] = "[{$key}] variable \"{$name}\" references missing model class {$variable->model}.";
            }

            if ($variable instanceof SelectVariable && $variable->options === []) {
                $this->warnings[] = "[{$key}] select variable \"{$name}\" has no options.";
            }
        }

        if ($definition->type === CommandType::Shell && ! config('command-center.shell.enabled', false)) {
            $this->errors[] = "[{$key}] is a shell command but shell execution is disabled.";
        }

        if ($definition->ability !== null && ! $this->gateExists($definition->ability)) {
            $this->errors[] = "[{$key}] requires ability \"{$definition->ability}\" but no such gate is defined.";
        }

        if ($definition->timeout < 1) {
            $this->errors[] = "[{$key}] has an invalid timeout of {$definition->timeout}.";
        }

        $maxSync = (int) config('command-center.max_sync_timeout', 30);

        if (! $definition->isQueued() && $definition->timeout > $maxSync) {
            $this->errors[] = "[{$key}] runs synchronously with a timeout of {$definition->timeout}s, "
                ."which exceeds max_sync_timeout ({$maxSync}s). Queue it or lower the timeout.";
        }

        if ($definition->isQueued() && config('queue.default') === 'sync') {
            $this->warnings[] = "[{$key}] is queued but QUEUE_CONNECTION is sync, so it will run inline.";
        }
    }

    private function gateExists(string $ability): bool
    {
        return Gate::has($ability);
    }
}
