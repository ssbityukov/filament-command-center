<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Support\Ansi;

it('leaves plain text alone', function (): void {
    expect(Ansi::strip('plain output'))->toBe('plain output');
});

it('removes colour codes', function (): void {
    expect(Ansi::strip("\033[32mINFO\033[39m done"))->toBe('INFO done');
});

it('removes cursor and erase sequences a progress bar leaves behind', function (): void {
    expect(Ansi::strip("10%\033[2K\033[1G20%"))->toBe('10%20%');
});

it('keeps newlines and tabs', function (): void {
    expect(Ansi::strip("one\ntwo\tthree"))->toBe("one\ntwo\tthree");
});

it('handles output with no sequences at all', function (): void {
    expect(Ansi::strip(''))->toBe('');
});
