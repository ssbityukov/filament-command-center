<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Execution\Cancellation;
use Bityukov\CommandCenter\Execution\RunProgress;

it('reports no progress for an unknown run', function (): void {
    expect(app(RunProgress::class)->get('run-1'))->toBeNull();
});

it('stores and reads progress', function (): void {
    app(RunProgress::class)->set('run-1', 40);

    expect(app(RunProgress::class)->get('run-1'))->toBe(40);
});

it('clamps progress into zero to one hundred', function (): void {
    app(RunProgress::class)->set('run-1', 250);
    expect(app(RunProgress::class)->get('run-1'))->toBe(100);

    app(RunProgress::class)->set('run-2', -5);
    expect(app(RunProgress::class)->get('run-2'))->toBe(0);
});

it('clears progress when set to null', function (): void {
    app(RunProgress::class)->set('run-1', 40);
    app(RunProgress::class)->set('run-1', null);

    expect(app(RunProgress::class)->get('run-1'))->toBeNull();
});

it('is not cancelled by default', function (): void {
    expect(app(Cancellation::class)->requested('run-1'))->toBeFalse();
});

it('records a cancellation request', function (): void {
    app(Cancellation::class)->request('run-1');

    expect(app(Cancellation::class)->requested('run-1'))->toBeTrue()
        ->and(app(Cancellation::class)->requested('run-2'))->toBeFalse();
});

it('forgets a cancellation request', function (): void {
    app(Cancellation::class)->request('run-1');
    app(Cancellation::class)->forget('run-1');

    expect(app(Cancellation::class)->requested('run-1'))->toBeFalse();
});
