<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-wrap items-center gap-4">
            <x-filament::badge :color="$this->stateColor()">
                {{ $this->record->state->label() }}
            </x-filament::badge>

            @if ($this->record->exitCode !== null)
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    Exit code {{ $this->record->exitCode }}
                </span>
            @endif

            @if ($this->record->durationMs !== null)
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ number_format($this->record->durationMs / 1000, 2) }}s
                </span>
            @endif

            @if ($this->record->startedAt)
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->record->startedAt->toDayDateTimeString() }}
                </span>
            @endif
        </div>

        @if ($this->record->error)
            <p class="mt-4 text-sm text-danger-600 dark:text-danger-400">
                {{ $this->record->error }}
            </p>
        @endif
    </x-filament::section>

    <x-filament::section heading="Output">
        <div @if ($this->pollInterval()) wire:poll.{{ $this->pollInterval() }}="refresh" @endif>
            @if ($this->isLive())
                @if ($this->progress() !== null)
                    <div class="mb-4">
                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                            <div
                                class="h-2 rounded-full bg-primary-600 transition-all"
                                style="width: {{ $this->progress() }}%"
                            ></div>
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $this->progress() }}%</p>
                    </div>
                @else
                    <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Running…</p>
                @endif
            @endif

            <x-command-center::output :output="$this->output()" />
        </div>
    </x-filament::section>

    <div class="flex gap-3">
        {{ $this->rerunAction }}
        {{ $this->cancelAction }}
    </div>
</x-filament-panels::page>
