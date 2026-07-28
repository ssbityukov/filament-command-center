<x-filament-panels::page>
    <x-filament::actions :actions="[$this->pruneAction]" alignment="end" />

    {{ $this->table }}
</x-filament-panels::page>
