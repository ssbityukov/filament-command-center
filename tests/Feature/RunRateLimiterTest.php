<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Definitions\CommandDefinition;
use Bityukov\CommandCenter\Execution\RunRateLimiter;

/**
 * @param  array{attempts: int, minutes: int}|null  $limit
 */
function rateLimited(?array $limit): CommandDefinition
{
    $command = Command::make('backup')->run('backup:run');

    if ($limit !== null) {
        $command->rateLimit($limit['attempts'], perMinutes: $limit['minutes']);
    }

    return $command->toDefinition(30);
}

it('allows a command with no limit', function (): void {
    $limiter = app(RunRateLimiter::class);

    foreach (range(1, 20) as $ignored) {
        expect($limiter->check(rateLimited(null), userId: 1))->toBeNull();
    }
});

it('allows attempts up to the limit', function (): void {
    $limiter = app(RunRateLimiter::class);
    $definition = rateLimited(['attempts' => 2, 'minutes' => 60]);

    expect($limiter->check($definition, userId: 1))->toBeNull()
        ->and($limiter->check($definition, userId: 1))->toBeNull();
});

it('refuses the attempt past the limit and reports a retry delay', function (): void {
    $limiter = app(RunRateLimiter::class);
    $definition = rateLimited(['attempts' => 1, 'minutes' => 60]);

    $limiter->check($definition, userId: 1);

    expect($limiter->check($definition, userId: 1))->toBeGreaterThan(0);
});

it('counts each user separately', function (): void {
    $limiter = app(RunRateLimiter::class);
    $definition = rateLimited(['attempts' => 1, 'minutes' => 60]);

    $limiter->check($definition, userId: 1);

    expect($limiter->check($definition, userId: 2))->toBeNull();
});

it('counts each command separately', function (): void {
    $limiter = app(RunRateLimiter::class);

    $limiter->check(rateLimited(['attempts' => 1, 'minutes' => 60]), userId: 1);

    $other = Command::make('other')->run('other:run')->rateLimit(1, perMinutes: 60)->toDefinition(30);

    expect($limiter->check($other, userId: 1))->toBeNull();
});

it('applies a global limit even to a command with none of its own', function (): void {
    config()->set('command-center.rate_limit.global', ['attempts' => 1, 'minutes' => 60]);

    $limiter = app(RunRateLimiter::class);

    expect($limiter->check(rateLimited(null), userId: 1))->toBeNull()
        ->and($limiter->check(rateLimited(null), userId: 1))->toBeGreaterThan(0);
});

it('treats a guest as one bucket rather than as unlimited', function (): void {
    $limiter = app(RunRateLimiter::class);
    $definition = rateLimited(['attempts' => 1, 'minutes' => 60]);

    $limiter->check($definition, userId: null);

    expect($limiter->check($definition, userId: null))->toBeGreaterThan(0);
});
