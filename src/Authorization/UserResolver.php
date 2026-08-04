<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Auth;

/**
 * Reloads a stored actor id through the auth provider that owns that id.
 *
 * Queued runs leave the panel request behind. Auth::getProvider() then points
 * at the application default guard, which is the wrong model when Command Center
 * lives on a Filament panel with its own authGuard (central admins, shop staff,
 * and so on). Worse than failing: the same id in the default table is a
 * different real person, and the gate would be asked about them. Callers pass
 * the guard that was in play at dispatch, or set command-center.auth_guard, so
 * the worker looks the user up in the same place.
 */
final class UserResolver
{
    public static function find(int|string $id, ?string $guard = null): ?Authenticatable
    {
        return self::provider(self::resolveGuard($guard))?->retrieveById($id);
    }

    public static function normalize(?string $guard): ?string
    {
        if ($guard === null || $guard === '') {
            return null;
        }

        return $guard;
    }

    /**
     * Prefer an explicit guard (panel / caller), then config, then the default
     * Auth provider.
     */
    public static function resolveGuard(?string $guard = null): ?string
    {
        return self::normalize($guard)
            ?? self::normalize(is_string($configured = config('command-center.auth_guard')) ? $configured : null);
    }

    /**
     * The guard's provider, not the guard itself.
     *
     * A guard is built for a request, which a worker does not have, and only
     * some of them expose the provider at all — anything built on RequestGuard,
     * token guards included, has no getProvider(). The provider named in the
     * guard's config is the piece that maps an id to a model, and it is safe to
     * build outside a request.
     */
    private static function provider(?string $guard): ?UserProvider
    {
        if ($guard === null) {
            return Auth::getProvider();
        }

        $provider = config("auth.guards.{$guard}.provider");

        if (! is_string($provider) || $provider === '') {
            return null;
        }

        // Null for a provider that is not configured, and deliberately not the
        // default provider: reading the wrong table is the bug this class exists
        // to close, and a caller with no actor denies the run.
        return Auth::createUserProvider($provider);
    }
}
