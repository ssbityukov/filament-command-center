<?php

declare(strict_types=1);

use Filament\Facades\Filament;

it('boots a filament panel in the package test suite', function (): void {
    expect(Filament::getPanel('test'))->not->toBeNull()
        ->and(Filament::getPanel('test')->getId())->toBe('test');
});

it('registers the package view namespace', function (): void {
    expect(app('view')->getFinder()->getHints())->toHaveKey('command-center');
});
