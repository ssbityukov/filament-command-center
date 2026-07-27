<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Flag;

it('derives a human label from the switch name', function (): void {
    $flag = Flag::make('--force');

    expect($flag->name)->toBe('--force')
        ->and($flag->label)->toBe('Force')
        ->and($flag->default)->toBeFalse()
        ->and($flag->help)->toBeNull();
});

it('derives a label from a multi word switch', function (): void {
    expect(Flag::make('--skip-cache')->label)->toBe('Skip cache');
});

it('accepts explicit label, default and help', function (): void {
    $flag = Flag::make('--force')
        ->label('Force it')
        ->default(true)
        ->help('Skips confirmation.');

    expect($flag->label)->toBe('Force it')
        ->and($flag->default)->toBeTrue()
        ->and($flag->help)->toBe('Skips confirmation.');
});

it('rejects a name that is not a switch', function (): void {
    Flag::make('force');
})->throws(InvalidArgumentException::class, 'Flag name must start with "--"');
