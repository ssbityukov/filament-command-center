<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Runs;

/**
 * Durable history.
 *
 * Run::toArray() is already the wire format for the cache driver, so it is the
 * column set here too — one shape, so a run written by one driver reads back
 * the same through the other.
 */
final class DatabaseRunStore implements RunStore
{
    public function put(Run $run): void
    {
        RunRecord::query()->updateOrCreate(['id' => $run->id], $this->columns($run));
    }

    public function find(string $id): ?Run
    {
        $record = RunRecord::query()->find($id);

        return $record instanceof RunRecord ? Run::fromArray($this->row($record)) : null;
    }

    /**
     * @return array<int, Run>
     */
    public function recent(int $limit = 100): array
    {
        return RunRecord::query()
            // Ordered by when the run started, not by insertion: a queued run is
            // written before it runs, and history reads chronologically.
            ->orderByDesc('started_at')
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (RunRecord $record): Run => Run::fromArray($this->row($record)))
            ->all();
    }

    public function forget(string $id): void
    {
        RunRecord::query()->whereKey($id)->delete();
    }

    public function flush(): void
    {
        RunRecord::query()->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function columns(Run $run): array
    {
        $data = $run->toArray();

        unset($data['id']);

        // The array form serialises timestamps to whole seconds. Writing the
        // date objects instead keeps the microseconds the column stores, which
        // is what makes ordering deterministic for runs started together.
        $data['started_at'] = $run->startedAt;
        $data['finished_at'] = $run->finishedAt;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(RunRecord $record): array
    {
        return [
            'id' => $record->id,
            'command_key' => $record->command_key,
            'label' => $record->label,
            // A string column hands back a string even when an int went in, and
            // history compares user ids to Auth::id().
            'user_id' => is_numeric($record->user_id) ? (int) $record->user_id : $record->user_id,
            'input' => $record->input,
            'argv' => $record->argv,
            'state' => $record->state,
            'started_at' => $record->started_at?->toIso8601String(),
            'finished_at' => $record->finished_at?->toIso8601String(),
            'duration_ms' => $record->duration_ms,
            'exit_code' => $record->exit_code,
            'output' => $record->output ?? '',
            'progress' => $record->progress,
            'error' => $record->error,
        ];
    }
}
