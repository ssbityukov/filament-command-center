<x-filament-panels::page>
    {{-- Result of the last run, in place: running a command should not send
         anyone to another page to find out what happened. --}}
    @if ($run = $this->lastRun())
        <div @if ($this->resultPollInterval()) wire:poll.{{ $this->resultPollInterval() }}="refreshResult" @endif>
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
            {{-- afterHeader is the slot Filament's section actually renders on the right --}}
            <x-slot name="afterHeader">
                @if ($run->durationMs !== null)
                    <x-filament::badge tag="span" color="gray" size="sm">
                        {{ number_format($run->durationMs / 1000, 2) }}s
                    </x-filament::badge>
                @endif

                @if ($run->output !== '')
                    {{-- The text rides in a data attribute rather than inside
                         x-data: Blade escapes it correctly there, and
                         navigator.clipboard is undefined over plain http, so
                         the textarea path is what actually copies. --}}
                    <x-filament::button
                        size="xs"
                        color="gray"
                        icon="heroicon-m-clipboard"
                        :data-output="$run->output"
                        x-on:click="
                            const text = $el.dataset.output ?? '';
                            const field = document.createElement('textarea');
                            field.value = text;
                            field.setAttribute('readonly', '');
                            field.style.position = 'fixed';
                            field.style.top = '-9999px';
                            document.body.appendChild(field);
                            field.select();
                            document.execCommand('copy');
                            document.body.removeChild(field);
                        "
                    >
                        Copy
                    </x-filament::button>
                @endif
            </x-slot>

            <x-command-center::output :output="$run->output" />
        </x-filament::section>
        </div>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
