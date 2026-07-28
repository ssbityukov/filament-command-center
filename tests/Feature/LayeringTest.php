<?php

declare(strict_types=1);

it('keeps filament out of the core', function (): void {
    exec(__DIR__.'/../../bin/assert-core-has-no-filament.sh 2>&1', $output, $exitCode);

    expect($exitCode)->toBe(0, implode(PHP_EOL, $output));
});

it('keeps shell execution primitives out of src', function (): void {
    exec(__DIR__.'/../../bin/assert-no-shell-commandline.sh 2>&1', $output, $exitCode);

    expect($exitCode)->toBe(0, implode(PHP_EOL, $output));
});
