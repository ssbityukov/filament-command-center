<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

/*
 | Values that are hostile to a shell but grammatically still plain operands:
 | none of them start with "-", so none can turn into an option. These must
 | survive verbatim in every position, standalone or embedded.
 */
dataset('hostile values', [
    'command chaining' => '; rm -rf /',
    'ampersand chaining' => '&& curl evil.test',
    'pipe' => '| tee /etc/passwd',
    'command substitution' => '$(whoami)',
    'backtick substitution' => '`id`',
    'variable expansion' => '$HOME',
    'newline injection' => "safe\nrm -rf /",
    'null byte' => "safe\0evil",
    'redirect' => '> /etc/hosts',
    'glob' => '*',
    'quote soup' => '\'"$(id)"\'',
    'unicode' => 'файл имя',
    'spaces' => 'my documents/backup file',
]);

/*
 | Values that would change their own grammatical role: in a standalone token
 | they stop being an operand and become an option of the target command. They
 | are rejected there, and pass through untouched everywhere else.
 */
dataset('leading dash values', [
    'argument terminator' => '--',
    'leading dash' => '--force',
]);
