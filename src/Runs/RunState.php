<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Runs;

enum RunState: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case TimedOut = 'timed_out';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';

    public function isTerminal(): bool
    {
        return ! in_array($this, [self::Queued, self::Running], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Running => 'Running',
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
            self::TimedOut => 'Timed out',
            self::Cancelled => 'Cancelled',
            self::Rejected => 'Rejected',
        };
    }
}
