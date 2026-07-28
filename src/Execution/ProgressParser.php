<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Execution;

/**
 * Progress requires the command to cooperate.
 *
 * Nothing is inferred from how much a command has printed — that would report
 * confident nonsense for a quiet command and for a chatty one alike. Either the
 * command emits the sentinel, or it prints a progress bar we can read, or the
 * UI shows an indeterminate bar and says nothing it cannot support.
 */
final class ProgressParser
{
    private const SENTINEL = '/##CC_PROGRESS:(\d{1,3})##/';

    private const BAR = '/\b(\d{1,3})%/';

    public function parse(string $text): ?int
    {
        return $this->lastMatch(self::SENTINEL, $text) ?? $this->lastMatch(self::BAR, $text);
    }

    private function lastMatch(string $pattern, string $text): ?int
    {
        if (preg_match_all($pattern, $text, $matches) < 1) {
            return null;
        }

        $last = end($matches[1]);

        return $last === false ? null : min((int) $last, 100);
    }
}
