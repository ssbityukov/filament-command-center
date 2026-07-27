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

        // Command::toDefinition() already rejects this, so a definition normally
        // never reaches here in that state — handle() reports the build failure
        // instead. The check stays because a source may construct a
        // CommandDefinition directly, and because reporting it as a plain error
        // beats an exception if it ever does.
        $firstElement = $this->firstElement($definition->run);

        if ($firstElement !== null && preg_match('/\{\w+\}/', $firstElement) === 1) {
            $this->errors[] = "[{$key}] uses a token in the command position of its run template; "
                .'the first element must be a literal so a submitted value cannot choose what executes.';
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

        // A warning, not an error: Gate::has() only sees explicitly defined
        // abilities, so a gate served by a Gate::before callback — how
        // spatie/laravel-permission and every super-admin catch-all work — looks
        // undefined here while authorizing correctly at runtime. An ability that
        // really is undefined fails closed anyway (Gate::allows returns false,
        // the command is hidden and denied), so this is a configuration smell
        // rather than a hole, and failing CI for it pushes adopters to delete
        // the very ability key the check exists to protect.
        if ($definition->ability !== null && ! $this->gateExists($definition->ability)) {
            $this->warnings[] = "[{$key}] requires ability \"{$definition->ability}\", which is not "
                .'explicitly defined as a gate. It may be served by a Gate::before callback; if it is '
                .'not, the command is denied to everyone.';
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

    private function firstElement(string $run): ?string
    {
        $elements = preg_split('/\s+/', trim($run), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $elements[0] ?? null;
    }
}
