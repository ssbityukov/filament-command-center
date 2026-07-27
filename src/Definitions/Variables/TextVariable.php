<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Definitions\Variables;

final class TextVariable extends Variable
{
    public static function make(string $name): self
    {
        return new self($name, self::humanise($name), false, null, false, null, []);
    }

    public function fieldType(): string
    {
        return 'text';
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
        );
    }
}
