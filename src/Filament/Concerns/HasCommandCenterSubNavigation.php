<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Filament\Concerns;

use Bityukov\CommandCenter\Filament\Pages\Commands;
use Bityukov\CommandCenter\Filament\Pages\History;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;

/**
 * Tabs inside the page, while the sidebar keeps its own entries.
 *
 * A cluster would give sub-navigation too, but at the cost of collapsing the
 * whole feature into a single sidebar item. Operators want both: the pages
 * visible where they look for them, and a way to move between them without
 * going back to the sidebar.
 */
trait HasCommandCenterSubNavigation
{
    /**
     * @return array<NavigationItem>
     */
    public function getSubNavigation(): array
    {
        return $this->generateNavigationItems([
            Commands::class,
            History::class,
        ]);
    }

    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return SubNavigationPosition::Top;
    }
}
