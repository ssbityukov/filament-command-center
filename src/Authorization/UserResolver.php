<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

/**
 * Reloads a stored actor id through the auth provider that owns that id.
 *
 * Queued runs leave the panel request behind. Auth::getProvider() then points
 * at the application default guard, which is the wrong model when Command Center
 * lives on a Filament panel with its own authGuard (central admins, shop staff,
 * and so on). Callers pass the guard that was in play at dispatch, or set
 * command-center.auth_guard, so the worker looks the user up in the same place.
 */
final class UserResolver
{
    public static function find(int|string $id, ?string $guard = null): ?Authenticatable
    {
        $guard = self::normalize($guard);

        if ($guard === null) {
            return Auth::getProvider()?->retrieveById($id);
        }

        return Auth::guard($guard)->getProvider()->retrieveById($id);
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
}
