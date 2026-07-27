<?php

declare(strict_types=1);

/*
 | Prints each argv element on its own line, prefixed by its index, so tests can
 | assert exactly how many arguments the process received and what each one is.
 | Exits with the code given by --exit=N, defaulting to 0.
 */

$exitCode = 0;

foreach (array_slice($argv, 1) as $index => $argument) {
    if (str_starts_with($argument, '--exit=')) {
        $exitCode = (int) substr($argument, 7);
    }

    echo $index.':'.$argument.PHP_EOL;
}

exit($exitCode);
