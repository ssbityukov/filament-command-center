<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Filament\Resources\CommandRecordResource\Pages;

use Bityukov\CommandCenter\Filament\Resources\CommandRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCommandRecord extends EditRecord
{
    protected static string $resource = CommandRecordResource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
