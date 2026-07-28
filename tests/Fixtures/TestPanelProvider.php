<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Tests\Fixtures;

use Bityukov\CommandCenter\Filament\CommandCenterPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('test')
            ->path('test')
            ->plugin(CommandCenterPlugin::make());
    }
}
