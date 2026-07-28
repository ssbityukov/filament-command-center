<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Filament\Pages;

use Bityukov\CommandCenter\Authorization\Authorizer;
use Bityukov\CommandCenter\Definitions\CommandDefinition;
use Bityukov\CommandCenter\Filament\CommandCenterPlugin;
use Filament\Clusters\Cluster;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class Commands extends Page
{
    protected static ?string $slug = 'commands';

    protected static ?string $title = 'Commands';

    /**
     * Redeclared deliberately. A subclass shares its parent's static property
     * storage in PHP, so assigning through static::$cluster without this would
     * write to Filament\Pages\Page and put every page in the panel — the
     * cluster included — inside our cluster, which recurses forever.
     */
    protected static ?string $cluster = null;

    protected static bool $isDiscovered = false;

    protected string $view = 'command-center::pages.commands';

    public string $search = '';

    /**
     * The plugin decides whether pages live in the cluster, and it must do so
     * before Filament reads getCluster() during page registration. A static
     * setter is how Filament's own navigation properties are configured.
     *
     * @param  class-string<Cluster>|null  $cluster
     */
    public static function cluster(?string $cluster): void
    {
        static::$cluster = $cluster;
    }

    public static function canAccess(): bool
    {
        return CommandCenterPlugin::forCurrentPanel()?->canAccess() ?? true;
    }

    /**
     * Visible definitions, filtered by the search box, keyed by group label.
     *
     * Denied commands never reach this array, so they are absent from the
     * rendered payload rather than hidden in it. The run action re-checks
     * authorization regardless — this is UX, not the boundary.
     *
     * @return array<string, array<int, CommandDefinition>>
     */
    public function groups(): array
    {
        $groups = [];

        foreach (app(Authorizer::class)->visibleTo() as $definition) {
            if (! $this->matchesSearch($definition)) {
                continue;
            }

            $groups[$definition->group ?? 'Ungrouped'][] = $definition;
        }

        ksort($groups);

        return $groups;
    }

    private function matchesSearch(CommandDefinition $definition): bool
    {
        $search = trim($this->search);

        if ($search === '') {
            return true;
        }

        $haystack = mb_strtolower($definition->key.' '.$definition->label.' '.($definition->help ?? ''));

        return str_contains($haystack, mb_strtolower($search));
    }
}
