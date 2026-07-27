<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Authorization\Authorizer;
use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Definitions\CommandDefinition;
use Bityukov\CommandCenter\Exceptions\UnauthorizedCommandException;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;

function definition(?string $ability = null, ?Closure $visible = null): CommandDefinition
{
    return Command::make('x')->run('cache:clear')->ability($ability)->visible($visible)->toDefinition(60);
}

it('allows a command with no ability and no visibility closure', function (): void {
    expect(app(Authorizer::class)->allows(definition()))->toBeTrue();
});

it('denies a command whose gate denies', function (): void {
    Gate::define('run-backups', fn (?User $user): bool => false);

    expect(app(Authorizer::class)->allows(definition('run-backups')))->toBeFalse();
});

it('allows a command whose gate allows', function (): void {
    Gate::define('run-backups', fn (?User $user): bool => true);

    expect(app(Authorizer::class)->allows(definition('run-backups')))->toBeTrue();
});

it('denies when the visibility closure returns false', function (): void {
    expect(app(Authorizer::class)->allows(definition(null, fn (): bool => false)))->toBeFalse();
});

it('requires both the gate and the closure to pass', function (): void {
    Gate::define('run-backups', fn (?User $user): bool => true);

    expect(app(Authorizer::class)->allows(definition('run-backups', fn (): bool => false)))->toBeFalse();
});

it('throws from authorize when denied', function (): void {
    app(Authorizer::class)->authorize(definition(null, fn (): bool => false));
})->throws(UnauthorizedCommandException::class, 'x');

it('does not throw from authorize when allowed', function (): void {
    app(Authorizer::class)->authorize(definition());
})->throwsNoExceptions();

it('filters the registry down to visible commands', function (): void {
    Gate::define('secret', fn (?User $user): bool => false);

    config()->set('command-center.commands', [
        'public' => ['run' => 'cache:clear'],
        'hidden' => ['run' => 'cache:clear', 'ability' => 'secret'],
    ]);

    expect(array_keys(app(Authorizer::class)->visibleTo()))->toBe(['public']);
});
