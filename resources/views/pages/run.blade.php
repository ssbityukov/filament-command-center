<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            <x-filament::badge :color="$this->stateColor()">
                {{ $this->record->state->label() }}
            </x-filament::badge>
        </x-slot>

        <x-slot name="description">
            @if ($this->record->exitCode !== null)
                Exit code {{ $this->record->exitCode }}.
            @endif

            @if ($this->record->durationMs !== null)
                Took {{ number_format($this->record->durationMs / 1000, 2) }}s.
            @endif

            @if ($this->record->startedAt)
                Started {{ $this->record->startedAt->diffForHumans() }}.
            @endif
        </x-slot>

        @if ($this->record->error)
            <x-filament::section.description>
                {{ $this->record->error }}
            </x-filament::section.description>
        @endif
    </x-filament::section>

    <x-filament::section heading="Output">
        <div @if ($this->pollInterval()) wire:poll.{{ $this->pollInterval() }}="refresh" @endif>
            @if ($this->isLive() && $this->progress() !== null)
                <x-filament::section.description>
                    {{ $this->progress() }}% complete
                </x-filament::section.description>
            @elseif ($this->isLive())
                <x-filament::section.description>
                    Running…
                </x-filament::section.description>
            @endif

            <x-command-center::output :output="$this->output()" />
        </div>
    </x-filament::section>

    <x-filament::actions :actions="array_filter([$this->rerunAction, $this->cancelAction])" />
</x-filament-panels::page>
