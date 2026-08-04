<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Authorization\UserResolver;
use Bityukov\CommandCenter\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Auth;

it('uses the default auth provider when no guard is set', function (): void {
    $user = TestUser::create(['name' => 'Ada', 'email' => 'ada@test.dev', 'password' => 'x']);

    expect(UserResolver::find($user->id))->toBeInstanceOf(TestUser::class)
        ->and(UserResolver::find($user->id)?->is($user))->toBeTrue();
});

it('reloads the actor through a named auth guard', function (): void {
    config()->set('auth.guards.admin', [
        'driver' => 'session',
        'provider' => 'admins',
    ]);
    config()->set('auth.providers.admins', [
        'driver' => 'eloquent',
        'model' => TestUser::class,
    ]);

    $user = TestUser::create(['name' => 'Grace', 'email' => 'grace@test.dev', 'password' => 'x']);

    expect(UserResolver::find($user->id, 'admin'))->toBeInstanceOf(TestUser::class)
        ->and(UserResolver::find($user->id, 'admin')?->is($user))->toBeTrue()
        ->and(Auth::guard('admin')->getProvider()->retrieveById($user->id)?->is($user))->toBeTrue();
});

it('prefers an explicit guard over the configured fallback', function (): void {
    config()->set('command-center.auth_guard', 'central');

    expect(UserResolver::resolveGuard('admin'))->toBe('admin')
        ->and(UserResolver::resolveGuard(null))->toBe('central')
        ->and(UserResolver::resolveGuard(''))->toBe('central');
});

it('treats blank guards as the default provider', function (): void {
    config()->set('command-center.auth_guard', null);

    expect(UserResolver::resolveGuard(null))->toBeNull()
        ->and(UserResolver::resolveGuard(''))->toBeNull()
        ->and(UserResolver::normalize(''))->toBeNull();
});
