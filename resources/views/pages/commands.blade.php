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
                    {{-- navigator.clipboard exists only in a secure context, so
                         a panel served over plain http has none. The textarea
                         fallback is what actually copies there. --}}
                    <x-filament::button
                        size="xs"
                        color="gray"
                        icon="heroicon-m-clipboard"
                        x-data="{
                            output: @js($run->output),
                            copied: false,
                            copy() {
                                const done = () => {
                                    this.copied = true
                                    setTimeout(() => this.copied = false, 2000)
                                }

                                if (window.isSecureContext && navigator.clipboard) {
                                    navigator.clipboard.writeText(this.output).then(done)

                                    return
                                }

                                const field = document.createElement('textarea')
                                field.value = this.output
                                field.setAttribute('readonly', '')
                                field.style.position = 'fixed'
                                field.style.opacity = '0'
                                document.body.appendChild(field)
                                field.select()
                                document.execCommand('copy')
                                document.body.removeChild(field)
                                done()
                            },
                        }"
                        x-on:click="copy()"
                    >
                        <span x-show="! copied">Copy</span>
                        <span x-show="copied" x-cloak>Copied</span>
                    </x-filament::button>
                @endif
            </x-slot>

            <x-command-center::output :output="$run->output" />
        </x-filament::section>
        </div>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
