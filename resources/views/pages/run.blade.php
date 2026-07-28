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
        <x-command-center::output :output="$this->record->output" />
    </x-filament::section>

    <div>
        {{ $this->rerunAction }}
    </div>
</x-filament-panels::page>
