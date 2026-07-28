<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Filament\Pages;

use Bityukov\CommandCenter\Authorization\RunVisibility;
use Bityukov\CommandCenter\Filament\CommandCenterPlugin;
use Bityukov\CommandCenter\Filament\Concerns\HasCommandCenterSubNavigation;
use Bityukov\CommandCenter\Runs\Run;
use Bityukov\CommandCenter\Runs\RunState;
use Bityukov\CommandCenter\Runs\RunStore;
use Filament\Actions\Action;
use Filament\Clusters\Cluster;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class History extends Page implements HasTable
{
    use HasCommandCenterSubNavigation;
    use InteractsWithTable;

    protected static ?string $slug = 'history';

    protected static ?string $title = 'Run history';

    protected static bool $isDiscovered = false;

    /**
     * Redeclared so assigning through static::$cluster does not write to
     * Filament\Pages\Page, whose storage every page would otherwise share.
     */
    protected static ?string $cluster = null;

    protected string $view = 'command-center::pages.history';

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

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (array $filters, int $page, int $recordsPerPage): LengthAwarePaginator => $this->page(
                $this->rows($filters),
                $page,
                $recordsPerPage,
            ))
            ->columns([
                TextColumn::make('label')->label('Command'),
                TextColumn::make('state')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        RunState::Succeeded->value => 'success',
                        RunState::Failed->value, RunState::TimedOut->value, RunState::Rejected->value => 'danger',
                        RunState::Cancelled->value => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('user_id')->label('User'),
                TextColumn::make('started_at')->label('Started')->dateTime(),
                TextColumn::make('duration')->label('Duration'),
                TextColumn::make('exit_code')->label('Exit'),
            ])
            ->filters([
                SelectFilter::make('state')->options(
                    collect(RunState::cases())
                        ->mapWithKeys(fn (RunState $state): array => [$state->value => $state->label()])
                        ->all(),
                ),
                SelectFilter::make('command_key')->label('Command')->options(fn (): array => $this->commandOptions()),
            ])
            ->recordUrl(fn (array $record): string => RunView::getUrl(['run' => $record['id']]))
            ->recordActions([
                Action::make('delete')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    // Deleting a run destroys an audit record, so it needs the
                    // same ability as pruning the lot. Without this, any user
                    // who can open the page could erase history one row at a
                    // time while the guarded prune action sat next to it.
                    ->visible(fn (): bool => $this->canPrune())
                    ->action(function (array $record): void {
                        abort_unless($this->canPrune(), 403);

                        app(RunStore::class)->forget((string) $record['id']);
                    }),
            ]);
    }

    public function pruneAction(): Action
    {
        return Action::make('prune')
            ->label('Prune history')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (): bool => $this->canPrune())
            ->action(function (): void {
                abort_unless($this->canPrune(), 403);

                app(RunStore::class)->flush();
            });
    }

    private function canPrune(): bool
    {
        return Gate::allows((string) config('command-center.abilities.prune_history'));
    }

    /**
     * A page of rows, since a custom-data table paginates itself.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @return LengthAwarePaginator<string, array<string, mixed>>
     */
    private function page(array $rows, int $page, int $recordsPerPage): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            collect($rows)->forPage($page, $recordsPerPage),
            total: count($rows),
            perPage: $recordsPerPage,
            currentPage: $page,
        );
    }

    /**
     * The cache driver has no query engine, so filtering runs in memory over the
     * capped index. This is bounded by history.max and is the documented cost of
     * a zero-migration default.
     *
     * @param  array<string, array<string, mixed>>  $filters
     * @return array<string, array<string, mixed>>
     */
    private function rows(array $filters): array
    {
        $state = $filters['state']['value'] ?? null;
        $commandKey = $filters['command_key']['value'] ?? null;

        $rows = [];

        // Filtered by visibility first: a run carries the argv and output of the
        // command it ran, so it is only for users authorized for that command.
        $visible = app(RunVisibility::class)->filter(
            app(RunStore::class)->recent((int) config('command-center.history.max', 100)),
        );

        foreach ($visible as $run) {
            if (filled($state) && $run->state->value !== $state) {
                continue;
            }

            if (filled($commandKey) && $run->commandKey !== $commandKey) {
                continue;
            }

            $rows[$run->id] = $this->toRow($run);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(Run $run): array
    {
        return [
            'id' => $run->id,
            'label' => $run->label,
            'command_key' => $run->commandKey,
            'state' => $run->state->value,
            'user_id' => $run->userId,
            'started_at' => $run->startedAt,
            'duration' => $run->durationMs === null ? null : number_format($run->durationMs / 1000, 2).'s',
            'exit_code' => $run->exitCode,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function commandOptions(): array
    {
        $options = [];

        $visible = app(RunVisibility::class)->filter(
            app(RunStore::class)->recent((int) config('command-center.history.max', 100)),
        );

        foreach ($visible as $run) {
            $options[$run->commandKey] = $run->label;
        }

        return $options;
    }
}
