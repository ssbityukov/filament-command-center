<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Definitions;

use Bityukov\CommandCenter\Definitions\Variables\Variable;
use Bityukov\CommandCenter\Exceptions\InvalidDefinitionException;
use Closure;

final class Command
{
    private string $label;

    private string $run = '';

    private CommandType $type = CommandType::Artisan;

    private ?string $group = null;

    private ?string $help = null;

    private ?int $timeout = null;

    private bool|string $queue = false;

    private ?string $ability = null;

    private ?Closure $visible = null;

    /** @var array<string, Variable> */
    private array $variables = [];

    /** @var array<string, Flag> */
    private array $flags = [];

    private ?int $concurrency = null;

    /** @var array{attempts: int, minutes: int}|null */
    private ?array $rateLimit = null;

    private bool|string $confirm = false;

    private bool $progress = false;

    private function __construct(private readonly string $key)
    {
        $this->label = ucfirst(str_replace(['_', '-'], ' ', $key));
    }

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function run(string $run): self
    {
        $this->run = $run;

        return $this;
    }

    public function shell(bool $shell = true): self
    {
        $this->type = $shell ? CommandType::Shell : CommandType::Artisan;

        return $this;
    }

    public function group(?string $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function help(?string $help): self
    {
        $this->help = $help;

        return $this;
    }

    public function timeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function queue(bool|string $queue = true): self
    {
        $this->queue = $queue;

        return $this;
    }

    public function ability(?string $ability): self
    {
        $this->ability = $ability;

        return $this;
    }

    public function visible(?Closure $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * @param  array<int, Variable>  $variables
     */
    public function variables(array $variables): self
    {
        foreach ($variables as $variable) {
            $this->variables[$variable->name] = $variable;
        }

        return $this;
    }

    /**
     * @param  array<int, Flag>  $flags
     */
    public function flags(array $flags): self
    {
        foreach ($flags as $flag) {
            $this->flags[$flag->name] = $flag;
        }

        return $this;
    }

    public function concurrency(?int $concurrency): self
    {
        $this->concurrency = $concurrency;

        return $this;
    }

    public function rateLimit(int $attempts, int $perMinutes): self
    {
        $this->rateLimit = ['attempts' => $attempts, 'minutes' => $perMinutes];

        return $this;
    }

    public function confirm(bool|string $confirm = true): self
    {
        $this->confirm = $confirm;

        return $this;
    }

    public function progress(bool $progress = true): self
    {
        $this->progress = $progress;

        return $this;
    }

    public function toDefinition(int $defaultTimeout): CommandDefinition
    {
        $this->assertCommandPositionIsLiteral();

        return new CommandDefinition(
            key: $this->key,
            label: $this->label,
            run: $this->run,
            type: $this->type,
            group: $this->group,
            help: $this->help,
            timeout: $this->timeout ?? $defaultTimeout,
            queue: $this->queue,
            ability: $this->ability,
            visible: $this->visible,
            variables: $this->variables,
            flags: $this->flags,
            concurrency: $this->concurrency,
            rateLimit: $this->rateLimit,
            confirm: $this->confirm,
            progress: $this->progress,
        );
    }

    /**
     * The first element of a run template names what executes — the Artisan
     * command, or the binary for a shell command. Allowing a token there would
     * hand the choice of program to whoever fills the form, which is precisely
     * what the allow-list exists to prevent. Enforced here rather than in a
     * parser so that every definition source is covered.
     */
    private function assertCommandPositionIsLiteral(): void
    {
        $elements = preg_split('/\s+/', trim($this->run), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($elements !== [] && preg_match('/\{\w+\}/', $elements[0]) === 1) {
            throw InvalidDefinitionException::tokenInCommandPosition($this->key);
        }
    }
}
