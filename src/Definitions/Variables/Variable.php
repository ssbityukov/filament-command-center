<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Definitions\Variables;

abstract class Variable
{
    /**
     * @param  array<int, string>  $rules
     */
    protected function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly bool $required,
        public readonly ?string $default,
        public readonly bool $redact,
        public readonly ?string $help,
        public readonly array $rules,
    ) {}

    abstract public function fieldType(): string;

    /**
     * Resolve a submitted value to its argv string, or null when absent.
     */
    public function resolve(mixed $value): ?string
    {
        $resolved = $this->normalise($value) ?? $this->default;

        return ($resolved === null || $resolved === '') ? null : $resolved;
    }

    protected function normalise(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    public function label(string $label): static
    {
        return $this->clone(label: $label);
    }

    public function required(bool $required = true): static
    {
        return $this->clone(required: $required);
    }

    public function default(?string $default): static
    {
        return $this->clone(default: $default);
    }

    public function redact(bool $redact = true): static
    {
        return $this->clone(redact: $redact);
    }

    public function help(?string $help): static
    {
        return $this->clone(help: $help);
    }

    /**
     * @param  array<int, string>  $rules
     */
    public function rules(array $rules): static
    {
        return $this->clone(rules: $rules);
    }

    /**
     * Subclasses override this to preserve their own extra properties.
     *
     * @param  array<int, string>|null  $rules
     */
    abstract protected function clone(
        ?string $label = null,
        ?bool $required = null,
        ?string $default = null,
        ?bool $redact = null,
        ?string $help = null,
        ?array $rules = null,
    ): static;

    protected static function humanise(string $name): string
    {
        return ucfirst(str_replace(['_', '-'], ' ', $name));
    }
}
