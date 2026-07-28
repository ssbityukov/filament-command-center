<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter;

use Bityukov\CommandCenter\Sources\ConfigSource;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CommandCenterServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('command-center')
            ->hasConfigFile('command-center')
            ->hasViews('command-center')
            ->hasMigration('create_command_center_runs_table')
            ->hasMigration('create_command_center_commands_table')
            ->hasCommand(Commands\CheckCommand::class)
            ->hasCommand(Commands\PruneCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->bind(ConfigSource::class, fn (): ConfigSource => new ConfigSource(
            commands: config('command-center.commands', []),
            defaultTimeout: (int) config('command-center.default_timeout', 30),
        ));

        // Scoped, not singleton: the registry memoizes every source's
        // definitions, and a queue worker or Octane process would otherwise hold
        // that memo for its whole lifetime. Scoped instances are reset between
        // jobs and requests, so a source whose data changes — a database-backed
        // one, say — cannot keep serving revoked definitions.
        $this->app->scoped(CommandRegistry::class, function ($app): CommandRegistry {
            $sources = array_map(
                static fn (string $source) => $app->make($source),
                config('command-center.sources', []),
            );

            return new CommandRegistry($sources);
        });

        $this->app->scoped(Runs\RunStore::class, function ($app): Runs\RunStore {
            if (config('command-center.history.driver') === 'database') {
                return new Runs\DatabaseRunStore;
            }

            return new Runs\CacheRunStore(
                cache: $app->make('cache')->store(config('command-center.history.store')),
                max: (int) config('command-center.history.max', 100),
                ttlHours: (int) config('command-center.history.ttl_hours', 168),
            );
        });

        $this->app->singleton(Execution\OutputBuffer::class, fn ($app): Execution\OutputBuffer => new Execution\OutputBuffer(
            $app->make('cache')->store(config('command-center.history.store')),
        ));

        $this->app->singleton(Execution\RunProgress::class, fn ($app): Execution\RunProgress => new Execution\RunProgress(
            $app->make('cache')->store(config('command-center.history.store')),
        ));

        $this->app->singleton(Execution\Cancellation::class, fn ($app): Execution\Cancellation => new Execution\Cancellation(
            $app->make('cache')->store(config('command-center.history.store')),
        ));

        $this->app->singleton(Execution\ProgressParser::class);

        $this->app->bind(Sources\DatabaseSource::class, fn (): Sources\DatabaseSource => new Sources\DatabaseSource(
            defaultTimeout: (int) config('command-center.default_timeout', 30),
        ));

        $this->app->singleton(Authorization\Authorizer::class);

        $this->app->singleton(Execution\ArgvBuilder::class);
        $this->app->singleton(Execution\ProcessFactory::class);
        $this->app->singleton(Execution\CommandRunner::class);
    }
}
