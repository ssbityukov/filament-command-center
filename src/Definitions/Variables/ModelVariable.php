<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Definitions\Variables;

use Closure;

final class ModelVariable extends Variable
{
    /**
     * @param  array<int, string>  $rules
     */
    private function __construct(
        string $name,
        string $label,
        bool $required,
        ?string $default,
        bool $redact,
        ?string $help,
        array $rules,
        public readonly string $model,
        public readonly string $titleAttribute,
        public readonly string $valueAttribute,
        public readonly ?Closure $modifyQueryUsing,
    ) {
        parent::__construct($name, $label, $required, $default, $redact, $help, $rules);
    }

    public static function make(string $name): self
    {
        return new self($name, self::humanise($name), false, null, false, null, [], '', 'name', 'id', null);
    }

    /**
     * @param  class-string  $model
     */
    public function model(string $model): self
    {
        return $this->rebuild(model: $model);
    }

    public function titleAttribute(string $titleAttribute): self
    {
        return $this->rebuild(titleAttribute: $titleAttribute);
    }

    public function valueAttribute(string $valueAttribute): self
    {
        return $this->rebuild(valueAttribute: $valueAttribute);
    }

    public function modifyQueryUsing(?Closure $callback): self
    {
        return $this->rebuild(modifyQueryUsing: $callback);
    }

    public function fieldType(): string
    {
        return 'model';
    }

    private function rebuild(
        ?string $model = null,
        ?string $titleAttribute = null,
        ?string $valueAttribute = null,
        ?Closure $modifyQueryUsing = null,
    ): self {
        return new self(
            $this->name, $this->label, $this->required, $this->default,
            $this->redact, $this->help, $this->rules,
            $model ?? $this->model,
            $titleAttribute ?? $this->titleAttribute,
            $valueAttribute ?? $this->valueAttribute,
            $modifyQueryUsing ?? $this->modifyQueryUsing,
        );
    }

    protected function clone(
        ?string $label = null,
        ?bool $required = null,
        ?string $default = null,
        ?bool $redact = null,
        ?string $help = null,
        ?array $rules = null,
    ): static {
        return new self(
            $this->name,
            $label ?? $this->label,
            $required ?? $this->required,
            $default ?? $this->default,
            $redact ?? $this->redact,
            $help ?? $this->help,
            $rules ?? $this->rules,
            $this->model,
            $this->titleAttribute,
            $this->valueAttribute,
            $this->modifyQueryUsing,
        );
    }
}
