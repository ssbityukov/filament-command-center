<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Definitions;

use Bityukov\CommandCenter\Definitions\Variables\Variable;
use Closure;

final readonly class CommandDefinition
{
    /**
     * @param  array<string, Variable>  $variables
     * @param  array<string, Flag>  $flags
     * @param  array{attempts: int, minutes: int}|null  $rateLimit
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $run,
        public CommandType $type,
        public ?string $group,
        public ?string $help,
        public int $timeout,
        public bool|string $queue,
        public ?string $ability,
        public ?Closure $visible,
        public array $variables,
        public array $flags,
        public ?int $concurrency,
        public ?array $rateLimit,
        public bool|string $confirm,
        public bool $progress,
    ) {}

    public function variable(string $name): ?Variable
    {
        return $this->variables[$name] ?? null;
    }

    public function isQueued(): bool
    {
        return $this->queue !== false;
    }

    public function queueName(): ?string
    {
        return is_string($this->queue) ? $this->queue : null;
    }

    /**
     * Unique token names found in the run template, in order of appearance.
     *
     * @return array<int, string>
     */
    public function tokens(): array
    {
        preg_match_all('/\{(\w+)\}/', $this->run, $matches);

        return array_values(array_unique($matches[1]));
    }
}
