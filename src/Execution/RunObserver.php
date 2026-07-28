<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Execution;

/**
 * The bridge between a running process and everything watching it.
 *
 * CommandRunner already had an output callback; this fills it in rather than
 * adding a second mechanism, so the execution core keeps one place where a
 * chunk of output arrives.
 *
 * Cancellation is not handled here: only the runner holds the Process it would
 * have to stop.
 */
final class RunObserver
{
    public function __construct(
        private readonly OutputBuffer $buffer,
        private readonly RunProgress $progress,
        private readonly ProgressParser $parser,
    ) {}

    /**
     * @return callable(string): void
     */
    public function for(string $runId): callable
    {
        return function (string $chunk) use ($runId): void {
            $this->buffer->append($runId, $chunk);

            $percent = $this->parser->parse($chunk);

            if ($percent !== null) {
                $this->progress->set($runId, $percent);
            }
        };
    }
}
