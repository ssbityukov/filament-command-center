<x-filament-panels::page>
    <x-filament::input.wrapper
        prefix-icon="heroicon-m-magnifying-glass"
        :valid="true"
    >
        <x-filament::input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Search commands"
        />
    </x-filament::input.wrapper>

    @forelse ($this->groups() as $group => $definitions)
        <x-filament::section
            :heading="$group"
            :description="count($definitions) . ' ' . str('command')->plural(count($definitions))"
            collapsible
            persist-collapsed
            :id="'command-center-group-' . \Illuminate\Support\Str::slug($group)"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($definitions as $definition)
                    <x-filament::card class="flex h-full flex-col gap-3">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="fi-section-header-heading text-base font-semibold leading-6">
                                {{ $definition->label }}
                            </h3>

                            @if ($definition->isQueued())
                                <x-filament::badge color="info" size="sm">Queued</x-filament::badge>
                            @endif
                        </div>

                        @if ($definition->help)
                            <p class="fi-section-header-description text-sm">
                                {{ $definition->help }}
                            </p>
                        @endif

                        {{-- The command itself, not a type badge: an operator
                             cares what will run, not how it is categorised. --}}
                        <code class="fi-badge block truncate rounded-md px-2 py-1 font-mono text-xs" title="{{ $definition->run }}">
                            {{ $definition->run }}
                        </code>

                        <div class="mt-auto pt-1">
                            {{ ($this->runAction)(['commandKey' => $definition->key]) }}
                        </div>
                    </x-filament::card>
                @endforeach
            </div>
        </x-filament::section>
    @empty
        <x-filament::section>
            <x-filament::empty-state
                icon="heroicon-o-command-line"
                heading="No commands available"
                :description="filled($this->search)
                    ? 'No command matches your search.'
                    : 'Commands you are allowed to run will appear here.'"
            />
        </x-filament::section>
    @endforelse
</x-filament-panels::page>
