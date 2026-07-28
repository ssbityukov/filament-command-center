<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Execution\ProgressParser;

it('finds nothing in plain output', function (): void {
    expect((new ProgressParser)->parse("working\nstill working"))->toBeNull();
});

it('reads the documented sentinel', function (): void {
    expect((new ProgressParser)->parse('##CC_PROGRESS:42##'))->toBe(42);
});

it('reads the sentinel embedded in other output', function (): void {
    expect((new ProgressParser)->parse("step one\n##CC_PROGRESS:7## done\nmore"))->toBe(7);
});

it('takes the last sentinel when several appear', function (): void {
    expect((new ProgressParser)->parse("##CC_PROGRESS:10##\n##CC_PROGRESS:90##"))->toBe(90);
});

it('reads a percentage from a progress bar line', function (): void {
    expect((new ProgressParser)->parse(' 12/50 [=====>------]  24%'))->toBe(24);
});

it('prefers the sentinel over a bar percentage', function (): void {
    expect((new ProgressParser)->parse("50% bar\n##CC_PROGRESS:99##"))->toBe(99);
});

it('clamps a sentinel above one hundred', function (): void {
    expect((new ProgressParser)->parse('##CC_PROGRESS:250##'))->toBe(100);
});

it('ignores a negative sentinel', function (): void {
    expect((new ProgressParser)->parse('##CC_PROGRESS:-5##'))->toBeNull();
});

it('does not read a percentage out of ordinary prose', function (): void {
    expect((new ProgressParser)->parse('cpu load is fine'))->toBeNull();
});
