<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Filament\Resources;

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Sources\CommandRecord;
use Bityukov\CommandCenter\Sources\DatabaseSource;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Throwable;

class CommandRecordResource extends Resource
{
    protected static ?string $model = CommandRecord::class;

    protected static ?string $slug = 'command-center/definitions';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?string $navigationLabel = 'Manage commands';

    /**
     * Redeclared so assigning the group does not write to Filament's base
     * Resource, which would move every resource in the panel into this group.
     */
    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = null;

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
            Section::make('Command')
                ->description('What runs, and whether the catalogue offers it at all.')
                ->columns(2)
                ->schema([
                    TextInput::make('key')
                        ->required()
                        ->alphaDash()
                        ->unique(ignoreRecord: true)
                        ->helperText('Used in URLs, locks and history. Cannot contain spaces.'),
                    Toggle::make('is_enabled')->default(true),
                    TextInput::make('definition.run')
                        ->label('Run template')
                        ->columnSpanFull()
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
                ]),

            Section::make('Presentation')
                ->description('How the command reads in the catalogue.')
                ->columns(2)
                ->schema([
                    TextInput::make('definition.label')->label('Label'),
                    TextInput::make('definition.group')->label('Group'),
                    Textarea::make('definition.help')
                        ->label('Help text')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Section::make('Execution')
                ->description('Limits and permissions applied every time it runs.')
                ->columns(2)
                ->schema([
                    TextInput::make('definition.timeout')
                        ->label('Timeout (seconds)')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('Past the synchronous limit the command has to be queued.'),
                    TextInput::make('definition.ability')
                        ->label('Required ability')
                        ->helperText('A gate ability. Without one, anyone with panel access can run this.'),
                ]),

            Section::make('Inputs')
                ->description('Values the operator supplies, and switches they can toggle.')
                ->schema([
                    Repeater::make('definition.variables')
                        ->label('Variables')
                        ->helperText('One per {token} in the run template.')
                        ->addActionLabel('Add variable')
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                        ->collapsible()
                        ->default([])
                        ->schema([
                            TextInput::make('name')
                                ->required()
                                ->alphaDash()
                                ->helperText('The {token} used in the run template.')
                                // A variable with no token is dead weight, and a token
                                // with no variable fails at run time. Caught here so the
                                // author finds out while editing.
                                ->rule(static fn (Get $get): callable => static function (string $attribute, mixed $value, callable $fail) use ($get): void {
                                    $run = (string) ($get('../../run') ?? '');

                                    if ($value !== null && $run !== '' && ! str_contains($run, '{'.$value.'}')) {
                                        $fail("The run template has no {{$value}} token.");
                                    }
                                }),
                            TextInput::make('label'),
                            Select::make('type')
                                ->required()
                                ->default('text')
                                ->live()
                                ->options([
                                    'text' => 'Text',
                                    'select' => 'Select',
                                    'boolean' => 'Toggle',
                                    'model' => 'Model (searchable)',
                                ]),
                            Toggle::make('required'),
                            TextInput::make('default'),
                            TextInput::make('help'),
                            Toggle::make('redact')
                                ->label('Keep out of history')
                                ->helperText('Still passed to the process; hidden from the run record.'),
                            KeyValue::make('options')
                                ->keyLabel('Value')
                                ->valueLabel('Label')
                                ->visible(fn (Get $get): bool => $get('type') === 'select'),
                            TextInput::make('model')
                                ->label('Model class')
                                ->visible(fn (Get $get): bool => $get('type') === 'model'),
                            TextInput::make('title_attribute')
                                ->label('Title attribute')
                                ->default('name')
                                ->visible(fn (Get $get): bool => $get('type') === 'model'),
                            TextInput::make('value_attribute')
                                ->label('Value attribute')
                                ->default('id')
                                ->visible(fn (Get $get): bool => $get('type') === 'model'),
                        ]),
                    KeyValue::make('definition.flags')
                        ->label('Flags')
                        ->keyLabel('Flag')
                        ->valueLabel('Label')
                        ->helperText('A checked flag is appended to the argv vector, e.g. --force.'),
                ]),
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
