<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Authorization;

use Bityukov\CommandCenter\CommandRegistry;
use Bityukov\CommandCenter\Runs\Run;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may see a recorded run.
 *
 * A run holds the argv and the output of a privileged command, so it is exactly
 * as sensitive as the command that produced it. Visibility therefore reuses the
 * command's own authorization rather than inventing a second rule: if you may
 * run it, you may read what it did.
 *
 * A run whose command has since disappeared from every source stays visible.
 * There is no ability left to check, and hiding history whenever an allow-list
 * entry is removed would quietly erase the audit trail the package exists to
 * keep.
 */
final class RunVisibility
{
    public function __construct(
        private readonly CommandRegistry $registry,
        private readonly Authorizer $authorizer,
    ) {}

    public function allows(Run $run, ?Authenticatable $user = null): bool
    {
        $definition = $this->registry->find($run->commandKey);

        return $definition === null || $this->authorizer->allows($definition, $user);
    }

    /**
     * @param  array<int, Run>  $runs
     * @return array<int, Run>
     */
    public function filter(array $runs, ?Authenticatable $user = null): array
    {
        return array_values(array_filter(
            $runs,
            fn (Run $run): bool => $this->allows($run, $user),
        ));
    }
}
