<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Filament\Pages\Commands;
use Bityukov\CommandCenter\Filament\SchemaBuilder;
use Bityukov\CommandCenter\Jobs\RunCommandJob;
use Bityukov\CommandCenter\Runs\RunState;
use Bityukov\CommandCenter\Runs\RunStore;
use Bityukov\CommandCenter\Tests\Fixtures\TestUser;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\PhpExecutableFinder;

use function Pest\Livewire\livewire;

function phpBinaryWithoutWhitespace(): string
{
    $php = (new PhpExecutableFinder)->find() ?: 'php';

    if (preg_match('/\s/', $php) !== 1) {
        return $php;
    }

    $link = sys_get_temp_dir().'/cc-test-php-'.md5($php);

    if (! file_exists($link)) {
        symlink($php, $link);
    }

    return $link;
}

beforeEach(function (): void {
    config()->set('command-center.shell.enabled', true);
    config()->set('command-center.commands', [
        'echo-value' => [
            'run' => phpBinaryWithoutWhitespace().' '.__DIR__.'/../Fixtures/echo-argv.php {payload}',
            'type' => 'shell',
            'label' => 'Echo value',
            'variables' => ['payload' => ['type' => 'text']],
        ],
        'guarded' => [
            'run' => phpBinaryWithoutWhitespace().' -v',
            'type' => 'shell',
            'label' => 'Guarded',
            'ability' => 'run-guarded',
        ],
        'queued' => [
            'run' => phpBinaryWithoutWhitespace().' -v',
            'type' => 'shell',
            'label' => 'Queued',
            'queue' => true,
        ],
    ]);

    $this->actingAs(TestUser::create([
        'name' => 'Ada',
        'email' => 'ada@test.dev',
        'password' => 'x',
    ]));
});

it('runs a command and stores the run', function (): void {
    livewire(Commands::class)
        ->callAction(TestAction::make('run')->arguments(['commandKey' => 'echo-value']), [
            'payload' => 'hello',
        ])
        ->assertNotified();

    $runs = app(RunStore::class)->recent();

    expect($runs)->toHaveCount(1)
        ->and($runs[0]->commandKey)->toBe('echo-value')
        ->and($runs[0]->state)->toBe(RunState::Succeeded)
        ->and($runs[0]->output)->toContain('0:hello');
});

it('delivers a hostile value as exactly one argv element', function (): void {
    livewire(Commands::class)
        ->callAction(TestAction::make('run')->arguments(['commandKey' => 'echo-value']), [
            'payload' => '; rm -rf / ; echo pwned',
        ]);

    $run = app(RunStore::class)->recent()[0];

    expect(rtrim($run->output, "\r\n"))->toBe('0:; rm -rf / ; echo pwned')
        ->and($run->argv)->toHaveCount(3);
});

it('records the acting user on the run', function (): void {
    livewire(Commands::class)
        ->callAction(TestAction::make('run')->arguments(['commandKey' => 'echo-value']), [
            'payload' => 'hello',
        ]);

    expect(app(RunStore::class)->recent()[0]->userId)->toBe(auth()->id());
});

it('refuses a crafted key for a command the user may not run', function (): void {
    Gate::define('run-guarded', fn (): bool => false);

    livewire(Commands::class)
        ->callAction(TestAction::make('run')->arguments(['commandKey' => 'guarded']))
        ->assertNotified();

    expect(app(RunStore::class)->recent())->toBe([]);
});

it('refuses a command key that exists in no source', function (): void {
    livewire(Commands::class)
        ->callAction(TestAction::make('run')->arguments(['commandKey' => 'no-such-command']))
        ->assertNotified();

    expect(app(RunStore::class)->recent())->toBe([]);
});

it('queues a queued command instead of running it inline', function (): void {
    Queue::fake();

    livewire(Commands::class)
        ->callAction(TestAction::make('run')->arguments(['commandKey' => 'queued']))
        ->assertNotified();

    $runs = app(RunStore::class)->recent();

    expect($runs)->toHaveCount(1)
        ->and($runs[0]->state)->toBe(RunState::Queued);

    Queue::assertPushed(RunCommandJob::class);
});

it('validates a required variable before running anything', function (): void {
    config()->set('command-center.commands.echo-value.variables.payload.required', true);

    livewire(Commands::class)
        ->callAction(TestAction::make('run')->arguments(['commandKey' => 'echo-value']), [
            'payload' => null,
        ])
        ->assertHasActionErrors(['payload' => 'required']);

    expect(app(RunStore::class)->recent())->toBe([]);
});

it('passes a checked flag through to the process', function (): void {
    config()->set('command-center.commands.echo-value.flags', ['--force' => ['label' => 'Force']]);

    livewire(Commands::class)
        ->callAction(TestAction::make('run')->arguments(['commandKey' => 'echo-value']), [
            'payload' => 'hello',
            SchemaBuilder::flagKey('--force') => true,
        ]);

    expect(app(RunStore::class)->recent()[0]->output)->toContain('1:--force');
});

it('omits an unchecked flag', function (): void {
    config()->set('command-center.commands.echo-value.flags', ['--force' => ['label' => 'Force']]);

    livewire(Commands::class)
        ->callAction(TestAction::make('run')->arguments(['commandKey' => 'echo-value']), [
            'payload' => 'hello',
            SchemaBuilder::flagKey('--force') => false,
        ]);

    expect(app(RunStore::class)->recent()[0]->output)->not->toContain('--force');
});

it('builds the modal schema from the definition variables', function (): void {
    config()->set('command-center.commands.echo-value.variables.payload.default', 'from-default');

    livewire(Commands::class)
        ->mountAction(TestAction::make('run')->arguments(['commandKey' => 'echo-value']))
        ->assertActionMounted(TestAction::make('run')->arguments(['commandKey' => 'echo-value']))
        ->assertSchemaStateSet(['payload' => 'from-default']);
});

it('previews the resolved command in the modal', function (): void {
    $page = livewire(Commands::class)->instance();

    expect($page->preview(['commandKey' => 'echo-value'], ['payload' => 'hello']))
        ->toEndWith(' hello');
});

it('previews the raw template when the input cannot be resolved yet', function (): void {
    config()->set('command-center.commands.echo-value.variables.payload.required', true);

    $preview = livewire(Commands::class)
        ->instance()
        ->preview(['commandKey' => 'echo-value'], ['payload' => null]);

    expect($preview)->toContain('{payload}');
});

it('does not run anything while previewing', function (): void {
    livewire(Commands::class)
        ->instance()
        ->preview(['commandKey' => 'echo-value'], ['payload' => 'hello']);

    expect(app(RunStore::class)->recent())->toBe([]);
});

it('redacts a redacted variable in the preview', function (): void {
    config()->set('command-center.commands.echo-value.variables.payload.redact', true);

    $preview = livewire(Commands::class)
        ->instance()
        ->preview(['commandKey' => 'echo-value'], ['payload' => 'secret-value']);

    expect($preview)->not->toContain('secret-value')
        ->and($preview)->toContain('[redacted]');
});

it('redacts a redacted variable in the stored run but still passes it to the process', function (): void {
    config()->set('command-center.commands.echo-value.variables.payload.redact', true);

    livewire(Commands::class)
        ->callAction(TestAction::make('run')->arguments(['commandKey' => 'echo-value']), [
            'payload' => 'secret-value',
        ]);

    $run = app(RunStore::class)->recent()[0];

    expect($run->input['payload'])->toBe('[redacted]')
        ->and($run->output)->toContain('0:secret-value');
});
