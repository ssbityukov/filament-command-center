<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Filament\Pages;

use Bityukov\CommandCenter\Authorization\RunVisibility;
use Bityukov\CommandCenter\CommandRegistry;
use Bityukov\CommandCenter\Filament\CommandCenterPlugin;
use Bityukov\CommandCenter\Runs\Run;
use Bityukov\CommandCenter\Runs\RunState;
use Bityukov\CommandCenter\Runs\RunStore;
use Filament\Actions\Action;
use Filament\Clusters\Cluster;
use Filament\Pages\Page;
use Filament\Panel;
use Livewire\Attributes\Computed;

/**
 * @property-read Run|null $record
 */
class RunView extends Page
{
    protected static ?string $slug = 'runs';

    protected static bool $isDiscovered = false;

    protected static bool $shouldRegisterNavigation = false;

    /**
     * Redeclared so assigning through static::$cluster does not write to
     * Filament\Pages\Page, whose storage every page would otherwise share.
     */
    protected static ?string $cluster = null;

    protected string $view = 'command-center::pages.run';

    /**
     * Only the id is component state. A Run is a plain value object, and
     * Livewire has no synthesizer for one, so it is re-read from the store on
     * each request instead of being serialised into the payload.
     */
    public string $runId = '';

    /**
     * @param  class-string<Cluster>|null  $cluster
     */
    public static function cluster(?string $cluster): void
    {
        static::$cluster = $cluster;
    }

    public static function canAccess(): bool
    {
        return CommandCenterPlugin::forCurrentPanel()?->canAccess() ?? true;
    }

    public static function getRoutePath(Panel $panel): string
    {
        return '/runs/{run}';
    }

    /**
     * A run is visible only to someone who may run the command it came from.
     *
     * Run records carry the full argv and the command's output, so exposing one
     * to a user who is not authorized for that command would hand them the
     * result of a privileged command through the back door. A run whose command
     * no longer exists in any source is treated as visible: there is no ability
     * left to check, and hiding history when an allow-list entry is removed
     * would quietly erase the audit trail.
     */
    public function mount(string $run): void
    {
        $record = app(RunStore::class)->find($run);

        abort_if($record === null, 404);
        abort_unless(app(RunVisibility::class)->allows($record), 404);

        $this->runId = $run;
    }

    #[Computed]
    public function record(): ?Run
    {
        return app(RunStore::class)->find($this->runId);
    }

    public function getTitle(): string
    {
        $record = $this->record();

        return $record === null ? 'Run' : $record->label;
    }

    public function stateColor(): string
    {
        return match ($this->record()?->state) {
            RunState::Succeeded => 'success',
            RunState::Failed, RunState::TimedOut, RunState::Rejected => 'danger',
            RunState::Cancelled => 'warning',
            default => 'info',
        };
    }

    /**
     * Re-runs restore the stored input. Redacted values were never stored, so
     * they come back as the redaction marker and the user retypes them.
     */
    public function rerunAction(): Action
    {
        return Action::make('rerun')
            ->label('Re-run')
            ->url(fn (): string => Commands::getUrl())
            ->visible(function (): bool {
                $record = $this->record();

                return $record !== null
                    && app(CommandRegistry::class)->find($record->commandKey) !== null;
            });
    }
}
