<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Filament\Pages;

use Filament\Clusters\Cluster;
use Filament\Pages\Page;

class Commands extends Page
{
    protected static ?string $slug = 'commands';

    protected static ?string $title = 'Commands';

    protected static bool $isDiscovered = false;

    protected string $view = 'command-center::pages.commands';

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
}
