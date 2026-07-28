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
            ->hasCommand(Commands\CheckCommand::class);
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

        $this->app->singleton(Authorization\Authorizer::class);

        $this->app->singleton(Execution\ArgvBuilder::class);
        $this->app->singleton(Execution\ProcessFactory::class);
        $this->app->singleton(Execution\CommandRunner::class);
    }
}
