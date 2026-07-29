<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Filament\Clusters\CommandCenterCluster;
use Bityukov\CommandCenter\Filament\CommandCenterPlugin;
use Bityukov\CommandCenter\Filament\Pages\Commands;
use Bityukov\CommandCenter\Filament\Pages\History;
use Bityukov\CommandCenter\Filament\Resources\CommandRecordResource;
use Bityukov\CommandCenter\Tests\Fixtures\TestUser;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Support\Facades\Gate;

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

it('puts both pages under one collapsible sidebar group', function (): void {
    expect(Commands::getCluster())->toBeNull()
        ->and(Commands::getNavigationGroup())->toBe('Command Center')
        ->and(History::getNavigationGroup())->toBe('Command Center');
});

it('drops the group when an app asks for top-level pages', function (): void {
    CommandCenterPlugin::make()->group(null)->register(Filament::getPanel('test'));

    expect(Commands::getNavigationGroup())->toBeNull();
});

it('gives each page an icon so it looks like the rest of the sidebar', function (): void {
    expect(Commands::getNavigationIcon())->not->toBeNull()
        ->and(History::getNavigationIcon())->not->toBeNull();
});

it('keeps its slugs under a prefix so they cannot collide with app routes', function (): void {
    expect(Commands::getSlug())->toStartWith('command-center/')
        ->and(History::getSlug())->toStartWith('command-center/');
});

it('puts pages in the cluster when clustering is asked for', function (): void {
    CommandCenterPlugin::make()->cluster()->register(Filament::getPanel('test'));

    expect(Commands::getCluster())->toBe(CommandCenterCluster::class);
});

it('groups the pages when an app asks for it', function (): void {
    CommandCenterPlugin::make()->group('Operations')->register(Filament::getPanel('test'));

    expect(Commands::getNavigationGroup())->toBe('Operations');
});

it('denies access when the configured ability is not granted', function (): void {
    // The panel decides who may reach the module, and an app that has said
    // nothing has not said yes. Installing the package must not hand the whole
    // feature to everyone who can open the panel.
    //
    // The suite defines the default ability for every other test, so this one
    // points the config at a gate nobody defined — which is exactly the state a
    // fresh install is in.
    config()->set('command-center.abilities.access', 'command-center:undefined-access');

    $this->actingAs(new TestUser(['name' => 'Ada']));

    expect(CommandCenterPlugin::make()->canAccess())->toBeFalse();
});

it('allows access when the configured ability is granted', function (): void {
    $this->actingAs(new TestUser(['name' => 'Ada']));

    Gate::define('command-center:access', fn (): bool => true);

    expect(CommandCenterPlugin::make()->canAccess())->toBeTrue();
});

it('allows access to everyone when the ability is set to null', function (): void {
    // The documented opt-out for a panel whose every user is already trusted.
    config()->set('command-center.abilities.access', null);

    expect(CommandCenterPlugin::make()->canAccess())->toBeTrue();
});

it('lets the authorize callback override the ability', function (): void {
    $this->actingAs(new TestUser(['name' => 'Ada']));

    Gate::define('command-center:access', fn (): bool => false);

    expect(CommandCenterPlugin::make()->authorize(fn (): bool => true)->canAccess())->toBeTrue();
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

it('does not drag unrelated pages into its sidebar group', function (): void {
    // Filament declares the navigation properties on its base Page, and a
    // subclass that does not redeclare them writes to that shared storage —
    // which silently moved every page in the panel, Dashboard included.
    CommandCenterPlugin::make()->register(Filament::getPanel('test'));

    expect(Dashboard::getNavigationGroup())->toBeNull()
        ->and(Dashboard::getNavigationLabel())->not->toBe('Commands');
});

it('keeps the command editor in the same sidebar group as the pages', function (): void {
    CommandCenterPlugin::make()->register(Filament::getPanel('test'));

    expect(CommandRecordResource::getNavigationGroup())
        ->toBe(Commands::getNavigationGroup());
});

it('orders the sidebar as commands, editor, history', function (): void {
    CommandCenterPlugin::make()->register(Filament::getPanel('test'));

    expect(Commands::getNavigationSort())
        ->toBeLessThan(CommandRecordResource::getNavigationSort())
        ->and(CommandRecordResource::getNavigationSort())
        ->toBeLessThan(History::getNavigationSort());
});
