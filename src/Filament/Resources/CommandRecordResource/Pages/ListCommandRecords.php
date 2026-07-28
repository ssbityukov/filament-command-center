<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Filament\Resources\CommandRecordResource\Pages;

use Bityukov\CommandCenter\Filament\Resources\CommandRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCommandRecords extends ListRecords
{
    protected static string $resource = CommandRecordResource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
