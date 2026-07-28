<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Filament\Pages;

use Bityukov\CommandCenter\Authorization\Authorizer;
use Bityukov\CommandCenter\CommandRegistry;
use Bityukov\CommandCenter\Definitions\CommandDefinition;
use Bityukov\CommandCenter\Execution\ArgvBuilder;
use Bityukov\CommandCenter\Execution\RunDispatcher;
use Bityukov\CommandCenter\Filament\CommandCenterPlugin;
use Bityukov\CommandCenter\Filament\SchemaBuilder;
use Bityukov\CommandCenter\Runs\Run;
use Bityukov\CommandCenter\Runs\RunState;
use Filament\Actions\Action;
use Filament\Clusters\Cluster;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;
use Throwable;

class Commands extends Page
{
    protected static ?string $slug = 'commands';

    protected static ?string $title = 'Commands';

    /**
     * Redeclared deliberately. A subclass shares its parent's static property
     * storage in PHP, so assigning through static::$cluster without this would
     * write to Filament\Pages\Page and put every page in the panel — the
     * cluster included — inside our cluster, which recurses forever.
     */
    protected static ?string $cluster = null;

    protected static bool $isDiscovered = false;

    protected string $view = 'command-center::pages.commands';

    public string $search = '';

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

            $groups[$definition->group ?? 'Ungrouped'][] = $definition;
        }

        ksort($groups);

        return $groups;
    }

    public function runAction(): Action
    {
        return Action::make('run')
            ->label('Run')
            ->modalHeading(function (array $arguments): string {
                $definition = $this->definitionFor($arguments);

                return $definition === null ? 'Run command' : $definition->label;
            })
            ->modalSubmitActionLabel('Run')
            ->fillForm(fn (array $arguments): array => $this->fillFor($arguments))
            ->schema(fn (array $arguments): array => $this->schemaFor($arguments))
            ->requiresConfirmation(fn (array $arguments): bool => $this->definitionFor($arguments)?->confirm !== false)
            ->modalDescription(function (array $arguments): ?string {
                $confirm = $this->definitionFor($arguments)?->confirm;

                return is_string($confirm) ? $confirm : null;
            })
            ->action(fn (array $arguments, array $data) => $this->execute($arguments, $data));
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
     * @param  array<string, mixed>  $arguments
     */
    private function definitionFor(array $arguments): ?CommandDefinition
    {
        $key = $arguments['commandKey'] ?? null;

        return is_string($key) ? app(CommandRegistry::class)->find($key) : null;
    }

    /**
     * The modal is the fields plus a display-only preview of what will actually
     * execute. The preview is built by the same ArgvBuilder the runner uses, so
     * it cannot drift from the real argv.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<int, Component>
     */
    private function schemaFor(array $arguments): array
    {
        $definition = $this->definitionFor($arguments);

        if ($definition === null) {
            return [];
        }

        $fields = array_map(
            fn (Component $field): Component => $field->live(onBlur: true),
            app(SchemaBuilder::class)->fields($definition),
        );

        $fields[] = Placeholder::make('preview')
            ->label('Command')
            ->content(fn (Get $get): string => $this->preview($arguments, $get()));

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function fillFor(array $arguments): array
    {
        $definition = $this->definitionFor($arguments);

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
     */
    private function execute(array $arguments, array $data): void
    {
        $definition = $this->definitionFor($arguments);

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

        $this->notifyOf($run);

        $this->redirect(RunView::getUrl(['run' => $run->id]));
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
