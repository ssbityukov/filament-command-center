<x-filament-panels::page>
    {{-- Result of the last run, in place: running a command should not send
         anyone to another page to find out what happened. --}}
    @if ($run = $this->lastRun())
        <x-filament::section :heading="$run->label" :description="$run->state->label()">
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
