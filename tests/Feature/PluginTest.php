<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Filament\Clusters\CommandCenterCluster;
use Bityukov\CommandCenter\Filament\CommandCenterPlugin;
use Bityukov\CommandCenter\Filament\Pages\Commands;
use Bityukov\CommandCenter\Tests\Fixtures\TestUser;
use Filament\Facades\Filament;

it('registers under a stable id', function (): void {
    expect(CommandCenterPlugin::make()->getId())->toBe('command-center');
});

it('is retrievable from the panel', function (): void {
    expect(Filament::getPanel('test')->getPlugin('command-center'))
        ->toBeInstanceOf(CommandCenterPlugin::class);
});

it('registers the commands page with the panel', function (): void {
    expect(Filament::getPanel('test')->getPages())->toContain(Commands::class);
});

it('puts pages in the cluster by default', function (): void {
    expect(Commands::getCluster())->toBe(CommandCenterCluster::class);
});

it('allows access when no authorize callback is set', function (): void {
    expect(CommandCenterPlugin::make()->canAccess())->toBeTrue();
});

it('denies access when the authorize callback returns false', function (): void {
    $this->actingAs(new TestUser(['name' => 'Ada']));

    $plugin = CommandCenterPlugin::make()->authorize(fn (): bool => false);

    expect($plugin->canAccess())->toBeFalse();
});

it('passes the authenticated user to the authorize callback', function (): void {
    $this->actingAs(new TestUser(['name' => 'Ada']));

    $seen = null;

    $plugin = CommandCenterPlugin::make()->authorize(function ($given) use (&$seen): bool {
        $seen = $given;

        return true;
    });

    $plugin->canAccess();

    expect($seen?->name)->toBe('Ada');
});

it('reports the navigation group configured on the plugin', function (): void {
    expect(CommandCenterPlugin::make()->navigationGroup('Ops')->getNavigationGroup())->toBe('Ops');
});
