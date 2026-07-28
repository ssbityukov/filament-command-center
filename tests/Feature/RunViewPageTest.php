<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Filament\Pages\RunView;
use Bityukov\CommandCenter\Runs\Run;
use Bityukov\CommandCenter\Runs\RunStore;
use Bityukov\CommandCenter\Tests\Fixtures\TestUser;
use Filament\Facades\Filament;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function Pest\Livewire\livewire;

function storeRun(string $output = 'all good', int $exitCode = 0): Run
{
    $definition = Command::make('route-list')
        ->label('List routes')
        ->run('route:list')
        ->toDefinition(30);

    $run = Run::start($definition, ['path' => 'up'], ['route:list'], userId: 1)
        ->finish($exitCode, $output);

    app(RunStore::class)->put($run);

    return $run;
}

beforeEach(function (): void {
    config()->set('command-center.commands', [
        'route-list' => ['run' => 'route:list', 'label' => 'List routes'],
    ]);

    $this->actingAs(new TestUser(['name' => 'Ada']));
});

it('renders a stored run', function (): void {
    $run = storeRun();

    livewire(RunView::class, ['run' => $run->id])
        ->assertOk()
        ->assertSee('List routes')
        ->assertSee('all good');
});

it('shows the exit code and state', function (): void {
    $run = storeRun(output: 'boom', exitCode: 2);

    livewire(RunView::class, ['run' => $run->id])
        ->assertSee('Failed')
        ->assertSee('2');
});

/*
 | mount() is called directly here rather than through livewire(): the Livewire
 | test renderer turns an abort into a 404 response instead of letting the
 | exception escape, which would make the assertion vacuous.
 */
it('aborts with 404 for an unknown run id', function (): void {
    (new RunView)->mount('no-such-run');
})->throws(NotFoundHttpException::class);

it('routes runs under a prefixed path with a run id parameter', function (): void {
    expect(RunView::getRoutePath(Filament::getPanel('test')))->toBe('/command-center/runs/{run}');
});

it('builds a url for a specific run', function (): void {
    $run = storeRun();

    expect(RunView::getUrl(['run' => $run->id]))->toContain($run->id);
});

it('escapes output rather than rendering it as html', function (): void {
    $run = storeRun(output: '<script>alert(1)</script>');

    livewire(RunView::class, ['run' => $run->id])
        ->assertDontSee('<script>alert(1)</script>', escape: false)
        ->assertSee('<script>alert(1)</script>');
});

it('offers a re-run action for a command that still exists', function (): void {
    $run = storeRun();

    livewire(RunView::class, ['run' => $run->id])
        ->assertActionExists('rerun');
});
