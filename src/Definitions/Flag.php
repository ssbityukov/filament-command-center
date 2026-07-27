<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Definitions;

use InvalidArgumentException;

final readonly class Flag
{
    private function __construct(
        public string $name,
        public string $label,
        public bool $default,
        public ?string $help,
    ) {}

    public static function make(string $name): self
    {
        if (! str_starts_with($name, '--')) {
            throw new InvalidArgumentException('Flag name must start with "--", got: '.$name);
        }

        return new self(
            name: $name,
            label: self::humanise($name),
            default: false,
            help: null,
        );
    }

    public function label(string $label): self
    {
        return new self($this->name, $label, $this->default, $this->help);
    }

    public function default(bool $default = true): self
    {
        return new self($this->name, $this->label, $default, $this->help);
    }

    public function help(?string $help): self
    {
        return new self($this->name, $this->label, $this->default, $help);
    }

    private static function humanise(string $name): string
    {
        return ucfirst(str_replace('-', ' ', ltrim($name, '-')));
    }
}
