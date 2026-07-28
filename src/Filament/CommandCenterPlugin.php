<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Filament;

use Bityukov\CommandCenter\Filament\Clusters\CommandCenterCluster;
use Bityukov\CommandCenter\Filament\Pages\Commands;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;

final class CommandCenterPlugin implements Plugin
{
    private bool $cluster = true;

    private ?Closure $authorizeUsing = null;

    private ?string $navigationGroup = null;

    private ?int $navigationSort = null;

    public static function make(): static
    {
        return app(self::class);
    }

    public function getId(): string
    {
        return 'command-center';
    }

    /**
     * The plugin instance registered on the panel in play.
     *
     * Page access is checked outside a panel request too — in tests, and by
     * navigation builders — so this falls back to the default panel rather than
     * silently reporting "no plugin, allow everything".
     */
    public static function forCurrentPanel(): ?self
    {
        $panel = Filament::getCurrentPanel() ?? Filament::getDefaultPanel();

        $plugin = $panel->hasPlugin('command-center') ? $panel->getPlugin('command-center') : null;

        return $plugin instanceof self ? $plugin : null;
    }

    public function cluster(bool $cluster = true): static
    {
        $this->cluster = $cluster;

        return $this;
    }

    /**
     * Panel-level access. Per-command authorization stays with the Authorizer;
     * this only decides whether the Command Center appears in this panel at all.
     */
    public function authorize(?Closure $callback): static
    {
        $this->authorizeUsing = $callback;

        return $this;
    }

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    public function canAccess(): bool
    {
        if ($this->authorizeUsing === null) {
            return true;
        }

        return (bool) ($this->authorizeUsing)(Auth::user());
    }

    public function register(Panel $panel): void
    {
        $cluster = $this->cluster ? CommandCenterCluster::class : null;

        Commands::cluster($cluster);

        if ($cluster === null) {
            Commands::navigationLabel('Commands');

            if ($this->navigationGroup !== null) {
                Commands::navigationGroup($this->navigationGroup);
            }

            if ($this->navigationSort !== null) {
                Commands::navigationSort($this->navigationSort);
            }
        }

        $pages = [Commands::class];

        if ($cluster !== null) {
            $pages[] = CommandCenterCluster::class;

            if ($this->navigationGroup !== null) {
                CommandCenterCluster::navigationGroup($this->navigationGroup);
            }

            if ($this->navigationSort !== null) {
                CommandCenterCluster::navigationSort($this->navigationSort);
            }
        }

        $panel->pages($pages);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
