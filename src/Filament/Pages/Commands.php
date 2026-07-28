<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Filament\Pages;

use Bityukov\CommandCenter\Authorization\Authorizer;
use Bityukov\CommandCenter\Authorization\RunVisibility;
use Bityukov\CommandCenter\CommandRegistry;
use Bityukov\CommandCenter\Definitions\CommandDefinition;
use Bityukov\CommandCenter\Execution\ArgvBuilder;
use Bityukov\CommandCenter\Execution\RunDispatcher;
use Bityukov\CommandCenter\Filament\CommandCenterPlugin;
use Bityukov\CommandCenter\Filament\SchemaBuilder;
use Bityukov\CommandCenter\Runs\Run;
use Bityukov\CommandCenter\Runs\RunState;
use Bityukov\CommandCenter\Runs\RunStore;
use Filament\Actions\Action;
use Filament\Clusters\Cluster;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize as TextColumnSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Throwable;

class Commands extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'command-center/commands';

    protected static ?string $title = 'Commands';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-command-line';

    /**
     * Redeclared deliberately. A subclass shares its parent's static property
     * storage in PHP, so assigning through static::$cluster without this would
     * write to Filament\Pages\Page and put every page in the panel — the
     * cluster included — inside our cluster, which recurses forever.
     */
    protected static ?string $cluster = null;

    /**
     * Redeclared for the same reason as $cluster: Filament declares these on its
     * base Page, and a subclass that assigns through static:: writes to that
     * shared storage — which moved every page in the panel, Dashboard included,
     * into this plugin's group.
     */
    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = null;

    protected static ?string $navigationLabel = null;

    protected static bool $isDiscovered = false;

    protected string $view = 'command-center::pages.commands';

    public string $search = '';

    public ?string $group = null;

    /**
     * The run whose result is shown at the top of the page.
     *
     * Kept here rather than redirecting: an operator who clears a cache wants
     * the outcome where they are, not a new page to navigate back from.
     */
    public ?string $lastRunId = null;

    /**
     * The plugin decides whether pages live in the cluster, and it must do so
     * before Filament reads getCluster() during page registration. A static
     * setter is how Filament's own navigation properties are configured.
     *
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

    /**
     * Visible definitions, filtered by the search box, keyed by group label.
     *
     * Denied commands never reach this array, so they are absent from the
     * rendered payload rather than hidden in it. The run action re-checks
     * authorization regardless — this is UX, not the boundary.
     *
     * @return array<string, array<int, CommandDefinition>>
     */
    public function groups(): array
    {
        $groups = [];

        foreach (app(Authorizer::class)->visibleTo() as $definition) {
            if (! $this->matchesSearch($definition)) {
                continue;
            }

            $name = $definition->group ?? 'Ungrouped';

            if ($this->group !== null && $this->group !== $name) {
                continue;
            }

            $groups[$name][] = $definition;
        }

        ksort($groups);

        return $groups;
    }

    public function runAction(): Action
    {
        return Action::make('run')
            ->label('Run')
            // A filled button with an icon, not the link a table action renders
            // by default: running the command is the point of the row.
            ->button()
            ->icon('heroicon-m-play')
            // The colour says what kind of run this is before you click:
            // red for anything the definition marked as needing confirmation,
            // blue for work that goes to a worker, primary for the rest.
            ->color(function (array $arguments, ?array $record = null): string {
                $definition = $this->definitionFor($arguments, $record);

                return match (true) {
                    $definition === null => 'warning',
                    $definition->confirm !== false => 'danger',
                    $definition->isQueued() => 'info',
                    default => 'warning',
                };
            })
            // A command with nothing to fill in and nothing to confirm runs on
            // the first click. Filament opens a modal as soon as an action has
            // a heading or a schema, so this says plainly when there is none.
            ->modalHidden(fn (array $arguments, ?array $record = null): bool => ! $this->needsModal($arguments, $record))
            ->modalHeading(function (array $arguments, ?array $record = null): string {
                $definition = $this->definitionFor($arguments, $record);

                return $definition === null ? 'Run command' : $definition->label;
            })
            ->fillForm(fn (array $arguments, ?array $record = null): array => $this->fillFor($arguments, $record))
            ->schema(fn (array $arguments, ?array $record = null): array => $this->schemaFor($arguments, $record))
            ->requiresConfirmation(fn (array $arguments, ?array $record = null): bool => $this->definitionFor($arguments, $record)?->confirm !== false)
            ->modalDescription(function (array $arguments, ?array $record = null): ?string {
                $confirm = $this->definitionFor($arguments, $record)?->confirm;

                return is_string($confirm) ? $confirm : null;
            })
            ->modalSubmitActionLabel('Run')
            ->action(fn (array $arguments, array $data, ?array $record = null) => $this->execute($arguments, $data, $record));
    }

    /**
     * Render what the command will run as, for display only.
     *
     * Nothing is executed and no value is escaped: each argv element is joined
     * with a single space purely so a human can read it. A value that would be
     * rejected by ArgvBuilder — a missing required variable, an out-of-scope
     * model id, a leading dash in an opening token — falls back to the raw
     * template rather than pretending the run would succeed.
     *
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $data
     */
    public function preview(array $arguments, array $data): string
    {
        $definition = $this->definitionFor($arguments);

        if ($definition === null) {
            return '';
        }

        try {
            $argv = app(ArgvBuilder::class)->build($definition, $this->previewInput($definition, $data));
        } catch (Throwable) {
            return $definition->run;
        }

        return implode(' ', $argv);
    }

    /**
     * A modal is only worth opening when it asks something: input to fill in,
     * or a confirmation to give.
     *
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>|null  $record
     */
    private function needsModal(array $arguments, ?array $record = null): bool
    {
        $definition = $this->definitionFor($arguments, $record);

        if ($definition === null) {
            return false;
        }

        return $definition->confirm !== false
            || $definition->variables !== []
            || $definition->flags !== [];
    }

    /**
     * The command key arrives as an action argument when the action is called
     * directly, and as the record id when the table renders it per row.
     *
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>|null  $record
     */
    private function definitionFor(array $arguments, ?array $record = null): ?CommandDefinition
    {
        $key = $arguments['commandKey'] ?? $record['id'] ?? null;

        return is_string($key) ? app(CommandRegistry::class)->find($key) : null;
    }

    /**
     * The modal is the fields plus a display-only preview of what will actually
     * execute. The preview is built by the same ArgvBuilder the runner uses, so
     * it cannot drift from the real argv.
     *
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>|null  $record
     * @return array<int, Component>
     */
    private function schemaFor(array $arguments, ?array $record = null): array
    {
        $definition = $this->definitionFor($arguments, $record);

        if ($definition === null) {
            return [];
        }

        $fields = array_map(
            fn (Component $field): Component => $field->live(onBlur: true),
            app(SchemaBuilder::class)->fields($definition),
        );

        $fields[] = Placeholder::make('preview')
            ->label('Command')
            ->content(fn (Get $get): string => $this->preview($arguments + ['commandKey' => $definition->key], $get()));

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>|null  $record
     * @return array<string, mixed>
     */
    private function fillFor(array $arguments, ?array $record = null): array
    {
        $definition = $this->definitionFor($arguments, $record);

        return $definition === null ? [] : app(SchemaBuilder::class)->defaults($definition);
    }

    /**
     * Run a command synchronously.
     *
     * Authorization is re-checked here rather than trusted from the catalogue:
     * a crafted Livewire call can name any key, including one that was never
     * rendered.
     *
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $record
     */
    private function execute(array $arguments, array $data, ?array $record = null): void
    {
        $definition = $this->definitionFor($arguments, $record);

        if ($definition === null) {
            $this->rejected('Unknown command.');

            return;
        }

        if (! app(Authorizer::class)->allows($definition)) {
            $this->rejected('You are not authorized to run this command.');

            return;
        }

        try {
            // The dispatcher owns rate limiting, the concurrency lock, the
            // queue-or-inline decision and recording the run. The page decides
            // none of that.
            $run = app(RunDispatcher::class)->dispatch(
                $definition,
                $this->toInput($definition, $data),
                Auth::id(),
            );
        } catch (Throwable $exception) {
            $this->rejected($exception->getMessage());

            return;
        }

        $this->lastRunId = $run->id;

        $this->notifyOf($run);
    }

    /**
     * Map form state back onto runner input: flag toggles carry sanitised state
     * keys and must become their command-line names again.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function toInput(CommandDefinition $definition, array $data): array
    {
        $input = [];

        foreach ($definition->variables as $variable) {
            $input[$variable->name] = $data[$variable->name] ?? null;
        }

        foreach ($definition->flags as $flag) {
            $input[$flag->name] = (bool) ($data[SchemaBuilder::flagKey($flag->name)] ?? false);
        }

        return $input;
    }

    /**
     * Preview input, with redacted values replaced before they can be rendered.
     * A redacted value is kept out of history and out of the screen alike.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function previewInput(CommandDefinition $definition, array $data): array
    {
        $input = $this->toInput($definition, $data);

        foreach ($definition->variables as $variable) {
            if ($variable->redact && filled($input[$variable->name] ?? null)) {
                $input[$variable->name] = '[redacted]';
            }
        }

        return $input;
    }

    private function notifyOf(Run $run): void
    {
        $notification = Notification::make()->title($run->label);

        match (true) {
            $run->state === RunState::Queued => $notification->info()->body('Queued.'),
            $run->state === RunState::Rejected => $notification->danger()->body($run->error ?? 'Rejected.'),
            $run->exitCode === 0 => $notification->success()->body('Finished successfully.'),
            default => $notification->danger()->body($run->error ?? 'Exit code '.($run->exitCode ?? 'unknown').'.'),
        };

        $notification->send();
    }

    private function rejected(string $reason): void
    {
        Notification::make()
            ->title('Command not run')
            ->body($reason)
            ->danger()
            ->send();
    }

    /**
     * The catalogue is a Filament table.
     *
     * Hand-written markup here meant hand-written Tailwind utilities, and those
     * never reach the panel's compiled CSS — a plugin's views are outside the
     * app's Tailwind sources, so the classes were silently dropped and the page
     * rendered unstyled. Everything below is built from components whose styles
     * already ship with Filament.
     */
    public function table(Table $table): Table
    {
        return $table
            // Filament injects the search term for a custom-data table but does
            // not apply it: with records() there is no query for it to filter.
            ->records(fn (array $filters, ?string $search): array => $this->rows($filters, $search))
            ->columns([
                TextColumn::make('label')
                    ->label('Command')
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (array $record): ?string => $record['help'])
                    ->searchable(),
                TextColumn::make('run')
                    ->label('Runs')
                    ->fontFamily(FontFamily::Mono)
                    ->size(TextColumnSize::ExtraSmall)
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('group')
                    ->label('Group')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('mode')
                    ->label('Mode')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Queued' ? 'info' : 'gray'),
            ])
            // Grouping by the definition's own group: the catalogue reads as
            // sections the way it did before it became a table, but the
            // grouping, counts and collapsing are Filament's.
            ->groups([
                Group::make('group')
                    ->label('Group')
                    ->collapsible(),
            ])
            ->defaultGroup('group')
            ->filters([
                SelectFilter::make('group')
                    ->label('Group')
                    ->options(fn (): array => array_combine(
                        array_keys($this->categories()),
                        array_keys($this->categories()),
                    )),
            ])
            ->recordActions([$this->runAction()])
            ->emptyStateHeading('No commands available')
            ->emptyStateDescription('Commands you are allowed to run will appear here.')
            ->emptyStateIcon('heroicon-o-command-line')
            ->paginated(false);
    }

    /**
     * @param  array<string, array<string, mixed>>  $filters
     * @return array<string, array<string, mixed>>
     */
    private function rows(array $filters, ?string $search = null): array
    {
        $group = $filters['group']['value'] ?? null;
        $term = trim((string) $search);

        $rows = [];

        foreach (app(Authorizer::class)->visibleTo() as $definition) {
            $name = $definition->group ?? 'Ungrouped';

            if (filled($group) && $group !== $name) {
                continue;
            }

            if ($term !== '' && ! $this->matches($definition, $term)) {
                continue;
            }

            $rows[$definition->key] = [
                'id' => $definition->key,
                'label' => $definition->label,
                'help' => $definition->help,
                'run' => $definition->run,
                'group' => $name,
                'mode' => $definition->isQueued() ? 'Queued' : 'Immediate',
            ];
        }

        return $rows;
    }

    private function matches(CommandDefinition $definition, string $term): bool
    {
        $haystack = mb_strtolower(implode(' ', [
            $definition->key,
            $definition->label,
            $definition->run,
            $definition->help ?? '',
            $definition->group ?? '',
        ]));

        return str_contains($haystack, mb_strtolower($term));
    }

    /**
     * Copy the output without leaving the page.
     *
     * The clipboard write happens in the browser through Filament's own
     * copyable support rather than through a round trip.
     */
    public function copyOutputAction(): Action
    {
        return Action::make('copyOutput')
            ->label('Copy')
            ->icon('heroicon-m-clipboard')
            ->link()
            ->size('xs')
            ->color('gray')
            ->visible(function (): bool {
                $run = $this->lastRun();

                return $run !== null && $run->output !== '';
            })
            ->action(function (): void {
                $run = $this->lastRun();

                $this->dispatch('cc-copy', output: $run === null ? '' : $run->output);

                Notification::make()->title('Output copied')->success()->send();
            });
    }

    public function lastRun(): ?Run
    {
        return $this->lastRunId === null ? null : app(RunStore::class)->find($this->lastRunId);
    }

    public function dismissLastRun(): void
    {
        $this->lastRunId = null;
    }

    /**
     * Group names with their counts, for the category rail.
     *
     * @return array<string, int>
     */
    public function categories(): array
    {
        $counts = [];

        foreach (app(Authorizer::class)->visibleTo() as $definition) {
            $name = $definition->group ?? 'Ungrouped';
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    public function selectGroup(?string $group): void
    {
        $this->group = $group;
    }

    /**
     * @return array<int, Run>
     */
    public function recentRuns(int $limit = 5): array
    {
        return app(RunVisibility::class)->filter(app(RunStore::class)->recent($limit * 3), null);
    }

    public function runViewUrl(Run $run): string
    {
        return RunView::getUrl(['run' => $run->id]);
    }

    private function matchesSearch(CommandDefinition $definition): bool
    {
        $search = trim($this->search);

        if ($search === '') {
            return true;
        }

        $haystack = mb_strtolower($definition->key.' '.$definition->label.' '.($definition->help ?? ''));

        return str_contains($haystack, mb_strtolower($search));
    }
}
