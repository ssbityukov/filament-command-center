<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Filament\Resources;

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Sources\CommandRecord;
use Bityukov\CommandCenter\Sources\DatabaseSource;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Throwable;

class CommandRecordResource extends Resource
{
    protected static ?string $model = CommandRecord::class;

    protected static ?string $slug = 'command-definitions';

    protected static ?string $modelLabel = 'command definition';

    public static function canAccess(): bool
    {
        // Two conditions, both required: the source has to be registered at all,
        // and the user has to hold the managing ability. An editor writing rows
        // no source reads is confusing; an unguarded one is a hole.
        $sources = config('command-center.sources', []);

        if (! in_array(DatabaseSource::class, is_array($sources) ? $sources : [], true)) {
            return false;
        }

        return Gate::allows((string) config('command-center.abilities.manage_commands'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')
                ->required()
                ->alphaDash()
                ->unique(ignoreRecord: true)
                ->helperText('Used in URLs, locks and history. Cannot contain spaces.'),
            Toggle::make('is_enabled')->default(true),
            TextInput::make('definition.label')->label('Label'),
            TextInput::make('definition.run')
                ->label('Run template')
                ->required()
                ->helperText('The first element must be a literal, e.g. backup:run {database}.')
                // Validated with the real builder, so the form cannot accept a
                // template the executor would refuse.
                ->rule(static fn (): callable => static function (string $attribute, mixed $value, callable $fail): void {
                    try {
                        Command::make('validation-probe')->run((string) $value)->toDefinition(30);
                    } catch (Throwable $exception) {
                        $fail($exception->getMessage());
                    }
                }),
            Select::make('definition.type')
                ->label('Type')
                ->options(['artisan' => 'Artisan', 'shell' => 'Shell'])
                ->default('artisan')
                ->required(),
            TextInput::make('definition.group')->label('Group'),
            Textarea::make('definition.help')->label('Help text'),
            TextInput::make('definition.timeout')->label('Timeout (seconds)')->numeric()->minValue(1),
            TextInput::make('definition.ability')->label('Required ability'),
            KeyValue::make('definition.flags')
                ->label('Flags')
                ->keyLabel('Flag')
                ->valueLabel('Label'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('key')->searchable(),
            TextColumn::make('definition.label')->label('Label'),
            IconColumn::make('is_enabled')->boolean()->label('Enabled'),
            TextColumn::make('updated_at')->dateTime()->label('Updated'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => CommandRecordResource\Pages\ListCommandRecords::route('/'),
            'create' => CommandRecordResource\Pages\CreateCommandRecord::route('/create'),
            'edit' => CommandRecordResource\Pages\EditCommandRecord::route('/{record}/edit'),
        ];
    }
}
