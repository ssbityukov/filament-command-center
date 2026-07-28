<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Commands;

use Bityukov\CommandCenter\Runs\RunRecord;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class PruneCommand extends Command
{
    protected $signature = 'command-center:prune {--days=30 : Delete runs that finished more than this many days ago}';

    protected $description = 'Delete old run records from the database history driver';

    public function handle(): int
    {
        if (config('command-center.history.driver') !== 'database') {
            $this->error(
                'Nothing to prune: the cache driver bounds itself by history.max and history.ttl_hours. '
                .'This command is for the database driver.'
            );

            return self::FAILURE;
        }

        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('--days must be at least 1.');

            return self::FAILURE;
        }

        // Only finished runs are eligible. A run with no finished_at is either
        // queued or still running, and deleting it would lose the record of
        // something that is currently happening.
        $deleted = RunRecord::query()
            ->whereNotNull('finished_at')
            ->where('finished_at', '<', CarbonImmutable::now()->subDays($days))
            ->delete();

        $this->info("Deleted {$deleted} run(s) finished more than {$days} day(s) ago.");

        return self::SUCCESS;
    }
}
