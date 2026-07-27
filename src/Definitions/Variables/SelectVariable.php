<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Definitions\Variables;

use Bityukov\CommandCenter\Exceptions\UnsafeValueException;

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
        bool $allowsLeadingDash,
        public readonly array $options,
    ) {
        parent::__construct($name, $label, $required, $default, $redact, $help, $rules, $allowsLeadingDash);
    }

    public static function make(string $name): self
    {
        return new self($name, self::humanise($name), false, null, false, null, [], false, []);
    }

    /**
     * @param  array<string, string>  $options
     */
    public function options(array $options): self
    {
        return new self(
            $this->name, $this->label, $this->required, $this->default,
            $this->redact, $this->help, $this->rules, $this->allowsLeadingDash, $options,
        );
    }

    public function fieldType(): string
    {
        return 'select';
    }

    /**
     * A select variable enforces its own allow-list.
     *
     * The options are the whole point of the field: presenting a closed set in
     * the UI and then accepting anything at the API boundary would recreate the
     * display/enforcement split this package exists to avoid. An empty options
     * array means the set was never declared, so nothing is enforced —
     * command-center:check warns about that separately.
     */
    public function resolve(mixed $value): ?string
    {
        $resolved = parent::resolve($value);

        if ($resolved === null || $this->options === []) {
            return $resolved;
        }

        if (! array_key_exists($resolved, $this->options)) {
            throw UnsafeValueException::notAnOption(
                $this->name,
                $resolved,
                array_map(strval(...), array_keys($this->options)),
            );
        }

        return $resolved;
    }

    protected function clone(
        ?string $label = null,
        ?bool $required = null,
        ?string $default = null,
        ?bool $redact = null,
        ?string $help = null,
        ?array $rules = null,
        ?bool $allowsLeadingDash = null,
    ): static {
        return new self(
            $this->name,
            $label ?? $this->label,
            $required ?? $this->required,
            $default ?? $this->default,
            $redact ?? $this->redact,
            $help ?? $this->help,
            $rules ?? $this->rules,
            $allowsLeadingDash ?? $this->allowsLeadingDash,
            $this->options,
        );
    }
}
