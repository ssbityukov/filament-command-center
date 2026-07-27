<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Authorization;

use Bityukov\CommandCenter\CommandRegistry;
use Bityukov\CommandCenter\Definitions\CommandDefinition;
use Bityukov\CommandCenter\Exceptions\UnauthorizedCommandException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class Authorizer
{
    public function __construct(private readonly CommandRegistry $registry) {}

    public function allows(CommandDefinition $definition, ?Authenticatable $user = null): bool
    {
        $user ??= Auth::user();

        if ($definition->ability !== null && ! Gate::forUser($user)->allows($definition->ability, [$definition])) {
            return false;
        }

        if ($definition->visible !== null && ! ($definition->visible)($user, $definition)) {
            return false;
        }

        return true;
    }

    public function authorize(CommandDefinition $definition, ?Authenticatable $user = null): void
    {
        if (! $this->allows($definition, $user)) {
            throw UnauthorizedCommandException::for($definition->key);
        }
    }

    /**
     * @return array<string, CommandDefinition>
     */
    public function visibleTo(?Authenticatable $user = null): array
    {
        return array_filter(
            $this->registry->all(),
            fn (CommandDefinition $definition): bool => $this->allows($definition, $user),
        );
    }
}
