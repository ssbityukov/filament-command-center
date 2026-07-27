<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Exceptions;

use RuntimeException;

final class ShellDisabledException extends RuntimeException
{
    public static function for(string $key): self
    {
        return new self(sprintf(
            'Command [%s] is a shell command, but shell execution is disabled. '
            .'Set command-center.shell.enabled to true to allow allow-listed shell commands.',
            $key,
        ));
    }
}
