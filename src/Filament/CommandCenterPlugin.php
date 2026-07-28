<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Filament;

use Bityukov\CommandCenter\Filament\Clusters\CommandCenterCluster;
use Bityukov\CommandCenter\Filament\Pages\Commands;
use Bityukov\CommandCenter\Filament\Pages\History;
use Bityukov\CommandCenter\Filament\Pages\RunView;
use Bityukov\CommandCenter\Filament\Resources\CommandRecordResource;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;

final class CommandCenterPlugin implements Plugin
{
    /**
     * Flat pages under a shared navigation group by default.
     *
     * A cluster collapses the whole feature into one sidebar entry and hides
     * the pages behind sub-navigation. Grouping keeps every page visible in the
     * sidebar, which is what an operator wants from a control panel.
     */
    private bool $cluster = false;

    private string $group = 'Command Center';

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

    /**
     * The sidebar group the pages sit under when they are not clustered.
     */
    public function group(string $group): static
    {
        $this->group = $group;

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
        History::cluster($cluster);
        RunView::cluster($cluster);

        if ($cluster === null) {
            Commands::navigationLabel('Commands');
            History::navigationLabel('Run history');

            // Applied to every page, not just the first: a group with one of its
            // entries missing reads as two unrelated features.
            $group = $this->navigationGroup ?? $this->group;

            Commands::navigationGroup($group);
            History::navigationGroup($group);

            Commands::navigationSort($this->navigationSort ?? 1);
            History::navigationSort(($this->navigationSort ?? 1) + 1);
        }

        $pages = [Commands::class, History::class, RunView::class];

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

        // Registered unconditionally; the resource's own canAccess() decides
        // whether it appears, so enabling the source is the only switch.
        $panel->resources([CommandRecordResource::class]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
