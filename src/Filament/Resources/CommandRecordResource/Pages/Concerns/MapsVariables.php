<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Filament\Resources\CommandRecordResource\Pages\Concerns;

/**
 * The editor lists variables; the parser expects them keyed by name.
 *
 * A repeater cannot express "keyed by a field of the item", so the two shapes
 * are converted at the form boundary rather than teaching the parser a second
 * format it would then have to keep supporting.
 */
trait MapsVariables
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function variablesToStorage(array $data): array
    {
        $variables = $data['definition']['variables'] ?? null;

        if (! is_array($variables)) {
            return $data;
        }

        $keyed = [];

        foreach ($variables as $variable) {
            if (! is_array($variable) || ! isset($variable['name'])) {
                continue;
            }

            $name = (string) $variable['name'];

            unset($variable['name']);

            $keyed[$name] = array_filter(
                $variable,
                static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== [],
            );
        }

        $data['definition']['variables'] = $keyed;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function variablesToForm(array $data): array
    {
        $variables = $data['definition']['variables'] ?? null;

        if (! is_array($variables)) {
            return $data;
        }

        $list = [];

        foreach ($variables as $name => $variable) {
            $list[] = ['name' => (string) $name] + (is_array($variable) ? $variable : []);
        }

        $data['definition']['variables'] = $list;

        return $data;
    }
}
