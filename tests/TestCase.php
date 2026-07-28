<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Tests;

use Bityukov\CommandCenter\CommandCenterServiceProvider;
use Bityukov\CommandCenter\Tests\Fixtures\TestPanelProvider;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Support\ViewErrorBag;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        /*
         | Order matters, and not alphabetically.
         |
         | Filament's SupportServiceProvider binds Livewire's DataStore to its
         | own DataStoreOverride with a plain bind(). Livewire's provider then
         | resolves that binding and pins the result as a shared instance.
         | Registering Livewire first inverts this: Filament's bind() lands last,
         | leaving DataStore unshared, so every app() call returns a fresh store
         | and component state — including the validation error bag — silently
         | vanishes between writes and reads.
         */
        return [
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            SupportServiceProvider::class,
            LivewireServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            CommandCenterServiceProvider::class,
            TestPanelProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Livewire reads the session-shared error bag when a component renders.
        // The Livewire test helper does not run route middleware, so nothing
        // shares it here; a real request always has it from
        // ShareErrorsFromSession.
        view()->share('errors', new ViewErrorBag);

        // A real request sets the current panel in Filament's middleware. The
        // Livewire test helper skips middleware, so pages would otherwise
        // render with no panel in context.
        Filament::setCurrentPanel('test');
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('auth.providers.users.model', Fixtures\TestUser::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
    }
}
