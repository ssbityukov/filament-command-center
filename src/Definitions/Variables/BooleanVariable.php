<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Definitions\Variables;

final class BooleanVariable extends Variable
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
        public readonly string $trueValue,
    ) {
        parent::__construct($name, $label, $required, $default, $redact, $help, $rules);
    }

    public static function make(string $name): self
    {
        return new self($name, self::humanise($name), false, null, false, null, [], '1');
    }

    public function trueValue(string $trueValue): self
    {
        return new self(
            $this->name, $this->label, $this->required, $this->default,
            $this->redact, $this->help, $this->rules, $trueValue,
        );
    }

    public function fieldType(): string
    {
        return 'boolean';
    }

    public function resolve(mixed $value): ?string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? $this->trueValue : null;
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
            $this->trueValue,
        );
    }
}
