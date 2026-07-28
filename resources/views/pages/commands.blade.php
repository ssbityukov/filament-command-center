<x-filament-panels::page>
    {{-- Result of the last run, in place. Running a command should not send
         anyone to another page to find out what happened. --}}
    @if ($run = $this->lastRun())
        <x-filament::section class="fi-cc-result">
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <span class="font-mono text-sm">{{ $run->label }}</span>

                    <x-filament::badge
                        size="sm"
                        :color="match ($run->state->value) {
                            'succeeded' => 'success',
                            'failed', 'timed_out', 'rejected' => 'danger',
                            'cancelled' => 'warning',
                            default => 'info',
                        }"
                    >
                        {{ $run->state->label() }}
                    </x-filament::badge>
                </div>
            </x-slot>

            <x-slot name="headerEnd">
                <div class="flex items-center gap-3">
                    @if ($run->durationMs !== null)
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ number_format($run->durationMs / 1000, 2) }}s
                        </span>
                    @endif

                    <x-filament::link :href="$this->runViewUrl($run)" size="xs">
                        Open run
                    </x-filament::link>

                    <x-filament::icon-button
                        icon="heroicon-m-x-mark"
                        wire:click="dismissLastRun"
                        label="Dismiss"
                        size="sm"
                        color="gray"
                    />
                </div>
            </x-slot>

            <x-command-center::output :output="$run->output" />

            @if ($run->error)
                <p class="mt-3 text-sm text-danger-600 dark:text-danger-400">{{ $run->error }}</p>
            @endif
        </x-filament::section>
    @endif

    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
        <x-filament::input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Search commands"
        />
    </x-filament::input.wrapper>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
        {{-- Categories --}}
        <aside class="lg:col-span-1">
            <x-filament::section>
                <ul class="space-y-1">
                    <li>
                        <button
                            type="button"
                            wire:click="selectGroup(null)"
                            @class([
                                'flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm',
                                'bg-gray-100 font-medium dark:bg-white/5' => $this->group === null,
                                'hover:bg-gray-50 dark:hover:bg-white/5' => $this->group !== null,
                            ])
                        >
                            <span>All commands</span>
                            <x-filament::badge size="sm" color="gray">
                                {{ array_sum($this->categories()) }}
                            </x-filament::badge>
                        </button>
                    </li>

                    @foreach ($this->categories() as $name => $count)
                        <li>
                            <button
                                type="button"
                                wire:click="selectGroup(@js($name))"
                                @class([
                                    'flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm',
                                    'bg-gray-100 font-medium dark:bg-white/5' => $this->group === $name,
                                    'hover:bg-gray-50 dark:hover:bg-white/5' => $this->group !== $name,
                                ])
                            >
                                <span class="truncate">{{ $name }}</span>
                                <x-filament::badge size="sm" color="gray">{{ $count }}</x-filament::badge>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
        </aside>

        {{-- Commands --}}
        <div class="space-y-6 lg:col-span-2">
            @forelse ($this->groups() as $group => $definitions)
                <x-filament::section
                    :heading="$group"
                    :badge="count($definitions)"
                    collapsible
                    persist-collapsed
                    :id="'cc-group-' . \Illuminate\Support\Str::slug($group)"
                >
                    <ul class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($definitions as $definition)
                            <li class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                                <div class="min-w-0 space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-filament::badge size="sm" color="gray">
                                            {{ strtoupper($definition->type->value) }}
                                        </x-filament::badge>

                                        <span class="truncate text-sm font-semibold">{{ $definition->label }}</span>

                                        @if ($definition->isQueued())
                                            <x-filament::badge size="sm" color="info">Queued</x-filament::badge>
                                        @endif
                                    </div>

                                    <p class="truncate font-mono text-xs text-gray-500 dark:text-gray-400" title="{{ $definition->run }}">
                                        {{ $definition->run }}
                                    </p>

                                    @if ($definition->help)
                                        <p class="truncate text-sm text-gray-500 dark:text-gray-400">
                                            {{ $definition->help }}
                                        </p>
                                    @endif
                                </div>

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
        </div>

        {{-- Recent runs --}}
        <aside class="lg:col-span-1">
            <x-filament::section heading="History">
                @forelse ($this->recentRuns() as $recent)
                    <a
                        href="{{ $this->runViewUrl($recent) }}"
                        class="-mx-2 flex items-start justify-between gap-2 rounded-lg px-2 py-2 hover:bg-gray-50 dark:hover:bg-white/5"
                    >
                        <span class="min-w-0">
                            <span class="block truncate text-sm">{{ $recent->label }}</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">
                                {{ $recent->startedAt?->diffForHumans() ?? 'queued' }}
                                @if ($recent->durationMs !== null)
                                    · {{ number_format($recent->durationMs / 1000, 2) }}s
                                @endif
                            </span>
                        </span>

                        <x-filament::badge
                            size="sm"
                            :color="match ($recent->state->value) {
                                'succeeded' => 'success',
                                'failed', 'timed_out', 'rejected' => 'danger',
                                'cancelled' => 'warning',
                                default => 'info',
                            }"
                        >
                            {{ $recent->state->label() }}
                        </x-filament::badge>
                    </a>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Nothing has run yet.</p>
                @endforelse
            </x-filament::section>
        </aside>
    </div>
</x-filament-panels::page>
