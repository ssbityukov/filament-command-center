<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            {{ $this->record->label }}

            <x-filament::badge tag="span" :color="$this->stateColor()">
                {{ $this->record->state->label() }}
            </x-filament::badge>
        </x-slot>

        {{-- One line, same shape as the result panel on the catalogue --}}
        <x-slot name="description">
            {{ collect([
                $this->record->exitCode === null ? null : 'Exit code '.$this->record->exitCode,
                $this->record->durationMs === null ? null : number_format($this->record->durationMs / 1000, 2).'s',
                $this->record->startedAt?->diffForHumans(),
            ])->filter()->implode(' · ') }}
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
