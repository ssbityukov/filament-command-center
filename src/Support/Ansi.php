<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Support;

/**
 * Strips terminal control sequences for display.
 *
 * Stored output keeps them: the record should hold what the process actually
 * wrote. Cleaning happens on the way to the screen, where the escape codes are
 * noise rather than information.
 */
final class Ansi
{
    private const PATTERN = "/\033\[[0-9;?]*[a-zA-Z]|\033\][^\007]*\007|\033[()][A-Za-z0-9]|[\x0E\x0F]/";

    public static function strip(string $text): string
    {
        return (string) preg_replace(self::PATTERN, '', $text);
    }
}
