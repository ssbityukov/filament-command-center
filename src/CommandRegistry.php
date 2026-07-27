<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter;

use Bityukov\CommandCenter\Definitions\CommandDefinition;
use Bityukov\CommandCenter\Exceptions\CommandNotFoundException;
use Bityukov\CommandCenter\Sources\CommandSource;

final class CommandRegistry
{
    /** @var array<int, CommandSource> */
    private array $sources = [];

    /** @var array<string, CommandDefinition>|null */
    private ?array $memo = null;

    /**
     * @param  array<int, CommandSource>  $sources
     */
    public function __construct(array $sources = [])
    {
        foreach ($sources as $source) {
            $this->addSource($source);
        }
    }

    public function addSource(CommandSource $source): void
    {
        $this->sources[] = $source;
        $this->memo = null;
    }

    /**
     * Definitions from every source. Later sources win on key collision.
     *
     * @return array<string, CommandDefinition>
     */
    public function all(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $definitions = [];

        foreach ($this->sources as $source) {
            $definitions = array_merge($definitions, $source->definitions());
        }

        return $this->memo = $definitions;
    }

    public function find(string $key): ?CommandDefinition
    {
        return $this->all()[$key] ?? null;
    }

    public function findOrFail(string $key): CommandDefinition
    {
        return $this->find($key) ?? throw CommandNotFoundException::for($key);
    }

    /**
     * @return array<string, array<string, CommandDefinition>>
     */
    public function grouped(): array
    {
        $grouped = [];

        foreach ($this->all() as $key => $definition) {
            $grouped[$definition->group ?? 'Commands'][$key] = $definition;
        }

        return $grouped;
    }

    /**
     * Drop the memoized definitions so the next call re-queries every source.
     *
     * Sources whose underlying data can change between calls — a database-backed
     * source, for instance — surface their changes after a flush. A config-backed
     * source will not, because it captures its array at construction and Laravel
     * config is static for the lifetime of a request.
     */
    public function flush(): void
    {
        $this->memo = null;
    }
}
