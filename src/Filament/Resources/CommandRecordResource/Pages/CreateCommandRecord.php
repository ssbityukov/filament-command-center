<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Filament\Resources\CommandRecordResource\Pages;

use Bityukov\CommandCenter\Filament\Resources\CommandRecordResource;
use Bityukov\CommandCenter\Filament\Resources\CommandRecordResource\Pages\Concerns\MapsVariables;
use Filament\Resources\Pages\CreateRecord;

class CreateCommandRecord extends CreateRecord
{
    use MapsVariables;

    protected static string $resource = CommandRecordResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->variablesToStorage($data);
    }
}
