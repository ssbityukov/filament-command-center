<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Execution\Cancellation;
use Bityukov\CommandCenter\Execution\OutputBuffer;
use Bityukov\CommandCenter\Execution\RunProgress;
use Bityukov\CommandCenter\Filament\Pages\RunView;
use Bityukov\CommandCenter\Runs\Run;
use Bityukov\CommandCenter\Runs\RunState;
use Bityukov\CommandCenter\Runs\RunStore;
use Bityukov\CommandCenter\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function Pest\Livewire\livewire;

function liveRun(RunState $state = RunState::Running): Run
{
    $definition = Command::make('slow')->label('Slow command')->run('slow:run')->toDefinition(30);

    $run = Run::queued($definition, [], ['slow:run'], userId: 1)->withId('live-run');

    if ($state === RunState::Running) {
        $run = $run->markRunning();
    }

    if ($state->isTerminal()) {
        $run = $run->markRunning()->finish(0, 'done');
    }

    app(RunStore::class)->put($run);

    return $run;
}

beforeEach(function (): void {
    config()->set('command-center.commands', [
        'slow' => ['run' => 'slow:run', 'label' => 'Slow command'],
    ]);

    $this->actingAs(new TestUser(['name' => 'Ada']));
});

it('polls while the run is not finished', function (): void {
    liveRun(RunState::Running);

    $page = livewire(RunView::class, ['run' => 'live-run'])->instance();

    expect($page->isLive())->toBeTrue()
        ->and($page->pollInterval())->toBe('750ms');
});

it('stops polling once the run is terminal', function (): void {
    liveRun(RunState::Succeeded);

    $page = livewire(RunView::class, ['run' => 'live-run'])->instance();

    expect($page->isLive())->toBeFalse()
        ->and($page->pollInterval())->toBeNull();
});

it('shows buffered output for a running run', function (): void {
    liveRun(RunState::Running);
    app(OutputBuffer::class)->append('live-run', 'streaming line');

    livewire(RunView::class, ['run' => 'live-run'])->assertSee('streaming line');
});

it('fetches only the bytes past the offset it already has', function (): void {
    liveRun(RunState::Running);
    app(OutputBuffer::class)->append('live-run', 'first ');

    $component = livewire(RunView::class, ['run' => 'live-run'])->call('refresh');

    app(OutputBuffer::class)->append('live-run', 'second');

    $component->call('refresh')
        ->assertSet('outputOffset', strlen('first second'))
        ->assertSet('liveOutput', 'first second');
});

it('shows progress reported by the running command', function (): void {
    liveRun(RunState::Running);
    app(RunProgress::class)->set('live-run', 42);

    expect(livewire(RunView::class, ['run' => 'live-run'])->instance()->progress())->toBe(42);
});

it('reports no progress when the command has said nothing', function (): void {
    liveRun(RunState::Running);

    expect(livewire(RunView::class, ['run' => 'live-run'])->instance()->progress())->toBeNull();
});

it('offers cancel while the run is live', function (): void {
    liveRun(RunState::Running);

    livewire(RunView::class, ['run' => 'live-run'])->assertActionVisible('cancel');
});

it('hides cancel once the run is terminal', function (): void {
    liveRun(RunState::Succeeded);

    livewire(RunView::class, ['run' => 'live-run'])->assertActionHidden('cancel');
});

it('requests cancellation through the shared flag', function (): void {
    liveRun(RunState::Running);

    livewire(RunView::class, ['run' => 'live-run'])->callAction('cancel');

    expect(app(Cancellation::class)->requested('live-run'))->toBeTrue();
});

it('refuses to open a run the user may not see', function (): void {
    config()->set('command-center.commands.slow.ability', 'run-slow');
    app()->forgetScopedInstances();
    Gate::define('run-slow', fn (): bool => false);

    liveRun(RunState::Running);

    (new RunView)->mount('live-run');
})->throws(NotFoundHttpException::class);
