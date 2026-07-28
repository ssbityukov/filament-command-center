<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Definitions\Variables;

use Bityukov\CommandCenter\Exceptions\UnknownModelValueException;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
        bool $allowsLeadingDash,
        public readonly string $model,
        public readonly string $titleAttribute,
        public readonly string $valueAttribute,
        public readonly ?Closure $modifyQueryUsing,
    ) {
        parent::__construct($name, $label, $required, $default, $redact, $help, $rules, $allowsLeadingDash);
    }

    public static function make(string $name): self
    {
        return new self($name, self::humanise($name), false, null, false, null, [], false, '', 'name', 'id', null);
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

    /**
     * The variable's own query, with modifyQueryUsing applied.
     *
     * Both the option list and resolution go through this single query, so the
     * set a user is shown and the set they may submit cannot drift apart.
     *
     * @return Builder<Model>
     */
    public function optionsQuery(): Builder
    {
        if ($this->model === '') {
            throw UnknownModelValueException::missingModel($this->name);
        }

        /** @var Model $model */
        $model = new $this->model;

        $query = $model->newQuery();

        if ($this->modifyQueryUsing !== null) {
            $query = ($this->modifyQueryUsing)($query) ?? $query;
        }

        return $query;
    }

    /**
     * @return array<array-key, string>
     */
    public function options(): array
    {
        return $this->optionsQuery()
            ->pluck($this->titleAttribute, $this->valueAttribute)
            ->map(fn (mixed $title): string => (string) $title)
            ->all();
    }

    /**
     * Matching records for a search term, as value => title.
     *
     * Capped, and queried rather than filtered in PHP: a select over a large
     * table would otherwise load every row into memory to render a dropdown.
     *
     * @return array<array-key, string>
     */
    public function search(string $term, int $limit = 50): array
    {
        return $this->optionsQuery()
            ->where($this->titleAttribute, 'like', '%'.$term.'%')
            ->limit($limit)
            ->pluck($this->titleAttribute, $this->valueAttribute)
            ->map(fn (mixed $title): string => (string) $title)
            ->all();
    }

    /**
     * The title for one stored value, so a saved selection still reads as a
     * name rather than as an id.
     */
    public function labelFor(mixed $value): ?string
    {
        $title = $this->optionsQuery()
            ->where($this->valueAttribute, $value)
            ->value($this->titleAttribute);

        return $title === null ? null : (string) $title;
    }

    /**
     * Re-resolve a submitted value through the variable's own query.
     *
     * Validating this in the UI would leave the Livewire boundary open: a
     * crafted request can name any value. A scoped select must therefore reject
     * an out-of-scope record here, where every caller goes through.
     */
    public function resolve(mixed $value): ?string
    {
        $resolved = parent::resolve($value);

        if ($resolved === null) {
            return null;
        }

        $exists = $this->optionsQuery()
            ->where($this->valueAttribute, $resolved)
            ->exists();

        if (! $exists) {
            throw UnknownModelValueException::for($this->name, $resolved);
        }

        return $resolved;
    }

    private function rebuild(
        ?string $model = null,
        ?string $titleAttribute = null,
        ?string $valueAttribute = null,
        ?Closure $modifyQueryUsing = null,
    ): self {
        return new self(
            $this->name, $this->label, $this->required, $this->default,
            $this->redact, $this->help, $this->rules, $this->allowsLeadingDash,
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
            $this->model,
            $this->titleAttribute,
            $this->valueAttribute,
            $this->modifyQueryUsing,
        );
    }
}
