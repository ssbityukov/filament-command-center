<x-filament-panels::page>
    {{-- Result of the last run, in place: running a command should not send
         anyone to another page to find out what happened. --}}
    @if ($run = $this->lastRun())
        <x-filament::section>
            <x-slot name="heading">
                {{ $run->label }}

                {{-- Colour and weight come from Filament's badge, which the
                     panel has compiled; a span with our own green would be
                     dropped from the stylesheet. --}}
                <x-filament::badge
                    tag="span"
                    :color="match ($run->state->value) {
                        'succeeded' => 'success',
                        'failed', 'timed_out', 'rejected' => 'danger',
                        'cancelled' => 'warning',
                        default => 'info',
                    }"
                >
                    {{ $run->state->label() }}
                </x-filament::badge>
            </x-slot>
            <x-slot name="headerEnd">
                <x-filament::link :href="$this->runViewUrl($run)" size="xs">
                    Open run
                </x-filament::link>
            </x-slot>

            <x-command-center::output :output="$run->output" />
        </x-filament::section>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
