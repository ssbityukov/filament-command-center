<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Authorization\UserResolver;
use Bityukov\CommandCenter\Tests\Fixtures\TestAdmin;
use Bityukov\CommandCenter\Tests\Fixtures\TestUser;

/*
 | Both tables hand out id 1, to two different people. Every test below asks for
 | id 1 and names whoever came back, because "the right model" is not something
 | a single shared model can demonstrate.
 */
beforeEach(function (): void {
    TestUser::create(['name' => 'Ada', 'email' => 'ada@test.dev', 'password' => 'x']);
    TestAdmin::create(['name' => 'Grace', 'email' => 'grace@test.dev', 'password' => 'x']);
});

it('uses the default auth provider when no guard is set', function (): void {
    expect(UserResolver::find(1))->toBeInstanceOf(TestUser::class)
        ->and(UserResolver::find(1)?->name)->toBe('Ada');
});

it('reloads the actor through a named auth guard', function (): void {
    expect(UserResolver::find(1, 'admin'))->toBeInstanceOf(TestAdmin::class)
        ->and(UserResolver::find(1, 'admin')?->name)->toBe('Grace');
});

it('reloads the actor through the configured guard when the caller names none', function (): void {
    config()->set('command-center.auth_guard', 'admin');

    expect(UserResolver::find(1))->toBeInstanceOf(TestAdmin::class)
        ->and(UserResolver::find(1)?->name)->toBe('Grace');
});

it('prefers an explicit guard over the configured fallback', function (): void {
    config()->set('command-center.auth_guard', 'admin');

    expect(UserResolver::find(1, 'web')?->name)->toBe('Ada')
        ->and(UserResolver::resolveGuard('web'))->toBe('web')
        ->and(UserResolver::resolveGuard(null))->toBe('admin')
        ->and(UserResolver::resolveGuard(''))->toBe('admin');
});

it('treats blank guards as the default provider', function (): void {
    config()->set('command-center.auth_guard', null);

    expect(UserResolver::resolveGuard(null))->toBeNull()
        ->and(UserResolver::resolveGuard(''))->toBeNull()
        ->and(UserResolver::normalize(''))->toBeNull();
});

/*
 | A guard that resolves to nothing yields no actor at all. Falling back to the
 | default provider here would hand back the wrong person under the right id —
 | the exact failure this resolver exists to prevent.
 */
it('finds no actor when the guard is not defined', function (): void {
    expect(UserResolver::find(1, 'nonexistent'))->toBeNull();
});

it('finds no actor when the guard names a provider that is not defined', function (): void {
    config()->set('auth.guards.ghost', [
        'driver' => 'session',
        'provider' => 'ghosts',
    ]);

    expect(UserResolver::find(1, 'ghost'))->toBeNull();
});
