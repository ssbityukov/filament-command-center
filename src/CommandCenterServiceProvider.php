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
            ->hasConfigFile('command-center');
    }

    public function packageRegistered(): void
    {
        $this->app->bind(ConfigSource::class, fn (): ConfigSource => new ConfigSource(
            commands: config('command-center.commands', []),
            defaultTimeout: (int) config('command-center.default_timeout', 60),
        ));

        $this->app->singleton(CommandRegistry::class, function ($app): CommandRegistry {
            $sources = array_map(
                static fn (string $source) => $app->make($source),
                config('command-center.sources', []),
            );

            return new CommandRegistry($sources);
        });

        $this->app->singleton(Authorization\Authorizer::class);
    }
}
