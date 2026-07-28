<x-filament-panels::page>
    <x-filament::input.wrapper>
        <x-filament::input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Search commands"
        />
    </x-filament::input.wrapper>

    @forelse ($this->groups() as $group => $definitions)
        <section class="space-y-3">
            <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                {{ $group }}
            </h2>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($definitions as $definition)
                    <x-filament::section>
                        <div class="space-y-2">
                            <h3 class="font-medium">{{ $definition->label }}</h3>

                            @if ($definition->help)
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $definition->help }}
                                </p>
                            @endif

                            <div class="flex flex-wrap gap-2">
                                <x-filament::badge size="sm">{{ $definition->type->value }}</x-filament::badge>

                                @if ($definition->isQueued())
                                    <x-filament::badge size="sm" color="info">queued</x-filament::badge>
                                @endif
                            </div>

                            {{ ($this->runAction)(['commandKey' => $definition->key]) }}
                        </div>
                    </x-filament::section>
                @endforeach
            </div>
        </section>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">
            No commands are available to you.
        </p>
    @endforelse
</x-filament-panels::page>
