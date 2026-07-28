<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Filament;

use Bityukov\CommandCenter\Definitions\CommandDefinition;
use Bityukov\CommandCenter\Definitions\Flag;
use Bityukov\CommandCenter\Definitions\Variables\ModelVariable;
use Bityukov\CommandCenter\Definitions\Variables\SelectVariable;
use Bityukov\CommandCenter\Definitions\Variables\Variable;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;

/**
 * The single place where a variable type becomes a Filament field.
 *
 * Pages never switch on fieldType() themselves, so adding a variable type is a
 * change here and nowhere else.
 */
final class SchemaBuilder
{
    /**
     * @return array<int, Component>
     */
    public function fields(CommandDefinition $definition): array
    {
        $fields = [];

        foreach ($definition->variables as $variable) {
            $fields[] = $this->field($variable);
        }

        foreach ($definition->flags as $flag) {
            $fields[] = $this->flagField($flag);
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(CommandDefinition $definition): array
    {
        $defaults = [];

        foreach ($definition->variables as $variable) {
            if ($variable->default !== null) {
                $defaults[$variable->name] = $variable->default;
            }
        }

        foreach ($definition->flags as $flag) {
            $defaults[self::flagKey($flag->name)] = $flag->default;
        }

        return $defaults;
    }

    /**
     * A flag is named on the command line (--force), which is not a legal
     * Livewire state path. The form carries a sanitised key and the page maps it
     * back before calling the runner.
     */
    public static function flagKey(string $name): string
    {
        return 'flag_'.str_replace('-', '_', ltrim($name, '-'));
    }

    private function field(Variable $variable): Field
    {
        $field = match ($variable->fieldType()) {
            'select' => $this->selectField($variable),
            'model' => $this->modelField($variable),
            'boolean' => Toggle::make($variable->name),
            default => $this->textField($variable),
        };

        return $field
            ->label($variable->label)
            ->helperText($this->helperFor($variable))
            ->required($variable->required)
            ->rules($variable->rules);
    }

    /**
     * Falls back to a hint the field cannot infer from its label alone.
     */
    private function helperFor(Variable $variable): ?string
    {
        if ($variable->help !== null) {
            return $variable->help;
        }

        if ($variable instanceof ModelVariable) {
            return 'Type at least part of the '.$variable->titleAttribute.' to search.';
        }

        return null;
    }

    private function textField(Variable $variable): TextInput
    {
        $field = TextInput::make($variable->name);

        // A redacted value is kept out of history; showing it in clear text in
        // the form would undo half of that, so it is masked at entry too.
        return $variable->redact ? $field->password()->revealable(false) : $field;
    }

    private function selectField(Variable $variable): Select
    {
        /** @var SelectVariable $variable */
        return Select::make($variable->name)->options($variable->options);
    }

    private function modelField(Variable $variable): Select
    {
        /** @var ModelVariable $variable */
        return Select::make($variable->name)
            ->searchable()
            // A searchable select starts empty, which reads as a broken field
            // unless it says what to type. The prompts name the attribute the
            // search actually runs against.
            ->placeholder('Start typing to search')
            ->searchPrompt('Search by '.$variable->titleAttribute)
            ->searchingMessage('Searching…')
            ->noSearchResultsMessage('Nothing matches that.')
            ->loadingMessage('Loading…')
            // Server-side search rather than a preloaded option list: the table
            // behind a model variable can be large, and rendering a dropdown
            // should not mean loading all of it.
            ->getSearchResultsUsing(fn (string $search): array => $variable->search($search))
            ->getOptionLabelUsing(fn (mixed $value): ?string => $variable->labelFor($value));
    }

    private function flagField(Flag $flag): Toggle
    {
        return Toggle::make(self::flagKey($flag->name))
            ->label($flag->label)
            ->helperText($flag->help)
            ->default($flag->default);
    }
}
