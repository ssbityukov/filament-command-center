<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Definitions\Variables;

final class SelectVariable extends Variable
{
    /**
     * @param  array<string, string>  $options
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
        public readonly array $options,
    ) {
        parent::__construct($name, $label, $required, $default, $redact, $help, $rules);
    }

    public static function make(string $name): self
    {
        return new self($name, self::humanise($name), false, null, false, null, [], []);
    }

    /**
     * @param  array<string, string>  $options
     */
    public function options(array $options): self
    {
        return new self(
            $this->name, $this->label, $this->required, $this->default,
            $this->redact, $this->help, $this->rules, $options,
        );
    }

    public function fieldType(): string
    {
        return 'select';
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
            $this->options,
        );
    }
}
