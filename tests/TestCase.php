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
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
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

        // The module is hidden until an application defines this gate, so the
        // suite defines it — the same line an adopting app writes. Tests that
        // are about the closed door define nothing and assert on that.
        Gate::define('command-center:access', fn (): bool => true);
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

        // A second guard over a second model, so the suite can prove that a run
        // reloads its actor through the guard it was dispatched from rather than
        // through the application default.
        $app['config']->set('auth.guards.admin', [
            'driver' => 'session',
            'provider' => 'admins',
        ]);
        $app['config']->set('auth.providers.admins', [
            'driver' => 'eloquent',
            'model' => Fixtures\TestAdmin::class,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();

        Schema::create('admins', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('email');
            $table->string('password');
        });

        // The package's own migrations are publishable rather than automatic,
        // so the suite loads them explicitly — the same thing an adopting app
        // does after vendor:publish.
        foreach (glob(__DIR__.'/../database/migrations/*.php.stub') ?: [] as $stub) {
            (include $stub)->up();
        }
    }
}
