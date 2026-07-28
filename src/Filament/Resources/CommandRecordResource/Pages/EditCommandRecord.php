<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Filament\Resources\CommandRecordResource\Pages;

use Bityukov\CommandCenter\Filament\Resources\CommandRecordResource;
use Bityukov\CommandCenter\Filament\Resources\CommandRecordResource\Pages\Concerns\MapsVariables;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCommandRecord extends EditRecord
{
    use MapsVariables;

    protected static string $resource = CommandRecordResource::class;

    /**
     * No breadcrumbs, to match the plugin's other pages.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->variablesToForm($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->variablesToStorage($data);
    }

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
