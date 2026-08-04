<?php

declare(strict_types=1);

use Bityukov\CommandCenter\CommandRegistry;
use Bityukov\CommandCenter\Jobs\RunCommandJob;
use Bityukov\CommandCenter\Runs\Run;
use Bityukov\CommandCenter\Runs\RunState;
use Bityukov\CommandCenter\Runs\RunStore;
use Bityukov\CommandCenter\Tests\Fixtures\TestAdmin;
use Bityukov\CommandCenter\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\Process\PhpExecutableFinder;

function jobPhpBinary(): string
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

function jobScript(string $php): string
{
    $path = sys_get_temp_dir().'/cc-job-'.md5($php).'.php';

    file_put_contents($path, '<?php '.$php);

    return jobPhpBinary().' '.$path;
}

beforeEach(function (): void {
    config()->set('command-center.shell.enabled', true);
    config()->set('command-center.commands', [
        'say' => [
            'run' => jobScript('echo "hello";'),
            'type' => 'shell',
            'label' => 'Say',
            'queue' => true,
        ],
        'guarded' => [
            'run' => jobScript('echo "guarded";'),
            'type' => 'shell',
            'label' => 'Guarded',
            'queue' => true,
            'ability' => 'run-guarded',
        ],
    ]);
});

function queuedRun(string $key, int|string|null $userId = 1): Run
{
    $definition = app(CommandRegistry::class)->findOrFail($key);

    $run = Run::queued($definition, [], [], $userId)->withId('job-run-'.$key);

    app(RunStore::class)->put($run);

    return $run;
}

it('runs the command through the container', function (): void {
    $run = queuedRun('say');

    app()->call([new RunCommandJob($run->id, 'say', [], 1), 'handle']);

    $stored = app(RunStore::class)->find($run->id);

    expect($stored?->state)->toBe(RunState::Succeeded)
        ->and($stored?->output)->toContain('hello');
});

it('leaves the run queued until the job is picked up', function (): void {
    $run = queuedRun('say');

    expect(app(RunStore::class)->find($run->id)?->state)->toBe(RunState::Queued);
});

it('rejects the run when the ability was revoked after dispatch', function (): void {
    Gate::define('run-guarded', fn (): bool => false);

    $user = TestUser::create(['name' => 'Ada', 'email' => 'ada@test.dev', 'password' => 'x']);

    $run = queuedRun('guarded', $user->id);

    app()->call([new RunCommandJob($run->id, 'guarded', [], $user->id), 'handle']);

    $stored = app(RunStore::class)->find($run->id);

    expect($stored?->state)->toBe(RunState::Rejected)
        ->and($stored?->error)->toContain('revoked');
});

it('rejects the run when the command has disappeared from every source', function (): void {
    $run = queuedRun('say');

    // A ConfigSource freezes its array at construction, so clearing the config
    // is not enough — the scoped registry and its source have to be rebuilt.
    config()->set('command-center.commands', []);
    app()->forgetScopedInstances();

    app()->call([new RunCommandJob($run->id, 'say', [], 1), 'handle']);

    expect(app(RunStore::class)->find($run->id)?->state)->toBe(RunState::Rejected);
});

it('never retries', function (): void {
    expect((new RunCommandJob('x', 'say', [], 1))->tries)->toBe(1);
});

it('gives the worker more time than the process is allowed', function (): void {
    config()->set('command-center.commands.say.timeout', 45);
    app()->forgetScopedInstances();

    expect((new RunCommandJob('x', 'say', [], 1))->timeout)->toBeGreaterThan(45);
});

/*
 | Id 1 is two different people: a denied user in the default table, an allowed
 | admin behind the panel's own guard. A job that resolves through the wrong one
 | does not merely fail to find somebody — it asks the gate about a stranger.
 */
function collidingActors(): void
{
    Gate::define('run-guarded', fn ($actor): bool => $actor->email === 'admin@test.dev');

    TestUser::create(['name' => 'Ada', 'email' => 'ada@test.dev', 'password' => 'x']);
    TestAdmin::create(['name' => 'Grace', 'email' => 'admin@test.dev', 'password' => 'x']);
}

it('reloads the actor through the auth guard captured on the job', function (): void {
    collidingActors();

    $run = queuedRun('guarded', 1);

    app()->call([new RunCommandJob($run->id, 'guarded', [], 1, 'admin'), 'handle']);

    expect(app(RunStore::class)->find($run->id)?->state)->toBe(RunState::Succeeded);
});

it('rejects the run when no guard sends it to the default provider', function (): void {
    collidingActors();

    $run = queuedRun('guarded', 1);

    app()->call([new RunCommandJob($run->id, 'guarded', [], 1), 'handle']);

    expect(app(RunStore::class)->find($run->id)?->state)->toBe(RunState::Rejected);
});

it('handles a job serialised before the guard was carried on the payload', function (): void {
    // An actor id, deliberately: the guard is only read while reloading one, so
    // a job with no actor would never touch the missing property.
    TestUser::create(['name' => 'Ada', 'email' => 'ada@test.dev', 'password' => 'x']);

    $run = queuedRun('say');

    $payload = serialize(new RunCommandJob($run->id, 'say', [], 1));
    $legacy = str_replace('s:9:"authGuard";N;', '', $payload);

    expect($legacy)->not->toBe($payload);

    $legacy = preg_replace_callback(
        '/^(O:\d+:"[^"]+":)(\d+)(:\{)/',
        fn (array $matches): string => $matches[1].((int) $matches[2] - 1).$matches[3],
        $legacy,
    );

    $job = unserialize((string) $legacy);

    app()->call([$job, 'handle']);

    expect(app(RunStore::class)->find($run->id)?->state)->toBe(RunState::Succeeded);
});
