<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Runs;

use Bityukov\CommandCenter\Definitions\CommandDefinition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final readonly class Run
{
    /**
     * Sentinel meaning "set progress to null", distinct from with()'s "null
     * leaves this field alone". Out of range for a percentage, so it can never
     * collide with a real value.
     */
    private const CLEAR_PROGRESS = PHP_INT_MIN;

    /**
     * @param  array<string, mixed>  $input
     * @param  array<int, string>  $argv
     */
    public function __construct(
        public string $id,
        public string $commandKey,
        public string $label,
        public int|string|null $userId,
        public array $input,
        public array $argv,
        public RunState $state,
        public ?CarbonImmutable $startedAt = null,
        public ?CarbonImmutable $finishedAt = null,
        public ?int $durationMs = null,
        public ?int $exitCode = null,
        public string $output = '',
        public ?int $progress = null,
        public ?string $error = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input  already redacted by the caller
     * @param  array<int, string>  $argv
     */
    public static function start(
        CommandDefinition $definition,
        array $input,
        array $argv,
        int|string|null $userId,
    ): self {
        return new self(
            id: (string) Str::uuid(),
            commandKey: $definition->key,
            label: $definition->label,
            userId: $userId,
            input: $input,
            argv: $argv,
            state: RunState::Running,
            startedAt: CarbonImmutable::now(),
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<int, string>  $argv
     */
    public static function queued(
        CommandDefinition $definition,
        array $input,
        array $argv,
        int|string|null $userId,
    ): self {
        return new self(
            id: (string) Str::uuid(),
            commandKey: $definition->key,
            label: $definition->label,
            userId: $userId,
            input: $input,
            argv: $argv,
            state: RunState::Queued,
        );
    }

    public static function rejected(
        CommandDefinition $definition,
        string $reason,
        int|string|null $userId,
    ): self {
        $now = CarbonImmutable::now();

        return new self(
            id: (string) Str::uuid(),
            commandKey: $definition->key,
            label: $definition->label,
            userId: $userId,
            input: [],
            argv: [],
            state: RunState::Rejected,
            startedAt: $now,
            finishedAt: $now,
            durationMs: 0,
            error: $reason,
        );
    }

    public function markRunning(): self
    {
        return $this->with(state: RunState::Running, startedAt: CarbonImmutable::now());
    }

    public function finish(int $exitCode, string $output): self
    {
        return $this->terminate(
            $exitCode === 0 ? RunState::Succeeded : RunState::Failed,
            $output,
            exitCode: $exitCode,
        );
    }

    public function timeout(string $output): self
    {
        return $this->terminate(RunState::TimedOut, $output, error: 'Command exceeded its timeout.');
    }

    public function cancel(string $output): self
    {
        return $this->terminate(RunState::Cancelled, $output, error: 'Cancelled by user.');
    }

    public function fail(string $error): self
    {
        return $this->terminate(RunState::Failed, $this->output, error: $error);
    }

    /**
     * Set the progress percentage, or clear it by passing null.
     *
     * Every other with() parameter treats null as "leave unchanged", which is
     * deliberate. Progress is the one field a caller genuinely needs to clear —
     * a run that stops reporting a percentage falls back to an indeterminate
     * bar — so it goes through a sentinel instead of widening with().
     */
    public function withProgress(?int $progress): self
    {
        return $this->with(progress: $progress ?? self::CLEAR_PROGRESS);
    }

    public function withOutput(string $output): self
    {
        return $this->with(output: $output);
    }

    private function terminate(
        RunState $state,
        string $output,
        ?int $exitCode = null,
        ?string $error = null,
    ): self {
        $finishedAt = CarbonImmutable::now();

        return $this->with(
            state: $state,
            finishedAt: $finishedAt,
            durationMs: $this->startedAt === null
                ? 0
                : (int) $this->startedAt->diffInMilliseconds($finishedAt, absolute: true),
            exitCode: $exitCode,
            output: $output,
            error: $error,
        );
    }

    private function with(
        ?RunState $state = null,
        ?CarbonImmutable $startedAt = null,
        ?CarbonImmutable $finishedAt = null,
        ?int $durationMs = null,
        ?int $exitCode = null,
        ?string $output = null,
        ?int $progress = null,
        ?string $error = null,
    ): self {
        return new self(
            id: $this->id,
            commandKey: $this->commandKey,
            label: $this->label,
            userId: $this->userId,
            input: $this->input,
            argv: $this->argv,
            state: $state ?? $this->state,
            startedAt: $startedAt ?? $this->startedAt,
            finishedAt: $finishedAt ?? $this->finishedAt,
            durationMs: $durationMs ?? $this->durationMs,
            exitCode: $exitCode ?? $this->exitCode,
            output: $output ?? $this->output,
            progress: match (true) {
                $progress === self::CLEAR_PROGRESS => null,
                $progress === null => $this->progress,
                default => $progress,
            },
            error: $error ?? $this->error,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'command_key' => $this->commandKey,
            'label' => $this->label,
            'user_id' => $this->userId,
            'input' => $this->input,
            'argv' => $this->argv,
            'state' => $this->state->value,
            'started_at' => $this->startedAt?->toIso8601String(),
            'finished_at' => $this->finishedAt?->toIso8601String(),
            'duration_ms' => $this->durationMs,
            'exit_code' => $this->exitCode,
            'output' => $this->output,
            'progress' => $this->progress,
            'error' => $this->error,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            commandKey: (string) $data['command_key'],
            label: (string) $data['label'],
            userId: $data['user_id'] ?? null,
            input: $data['input'] ?? [],
            argv: $data['argv'] ?? [],
            state: RunState::from((string) $data['state']),
            startedAt: isset($data['started_at']) ? CarbonImmutable::parse($data['started_at']) : null,
            finishedAt: isset($data['finished_at']) ? CarbonImmutable::parse($data['finished_at']) : null,
            durationMs: $data['duration_ms'] ?? null,
            exitCode: $data['exit_code'] ?? null,
            output: (string) ($data['output'] ?? ''),
            progress: $data['progress'] ?? null,
            error: $data['error'] ?? null,
        );
    }
}
