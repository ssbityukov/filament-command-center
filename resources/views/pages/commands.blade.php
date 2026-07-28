<x-filament-panels::page>
    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
        <x-filament::input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Search commands"
        />
    </x-filament::input.wrapper>

    @forelse ($this->groups() as $group => $definitions)
        <x-filament::section
            :heading="$group"
            :badge="count($definitions)"
            collapsible
            persist-collapsed
            :id="'cc-group-' . \Illuminate\Support\Str::slug($group)"
        >
            <ul class="fi-ta-content divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($definitions as $definition)
                    <li class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                        {{-- Everything explanatory on the left --}}
                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-filament::badge size="sm" color="gray">
                                    {{ strtoupper($definition->type->value) }}
                                </x-filament::badge>

                                <span class="truncate text-sm font-semibold">
                                    {{ $definition->label }}
                                </span>

                                @if ($definition->isQueued())
                                    <x-filament::badge size="sm" color="info">Queued</x-filament::badge>
                                @endif
                            </div>

                            {{-- The command itself: what will actually run --}}
                            <p class="truncate font-mono text-xs text-gray-500 dark:text-gray-400" title="{{ $definition->run }}">
                                {{ $definition->run }}
                            </p>

                            @if ($definition->help)
                                <p class="truncate text-sm text-gray-500 dark:text-gray-400">
                                    {{ $definition->help }}
                                </p>
                            @endif
                        </div>

                        {{-- Action on the right --}}
                        <div class="shrink-0">
                            {{ ($this->runAction)(['commandKey' => $definition->key]) }}
                        </div>
                    </li>
                @endforeach
            </ul>
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
