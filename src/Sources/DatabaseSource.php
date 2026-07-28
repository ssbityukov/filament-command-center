<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Sources;

use Bityukov\CommandCenter\Definitions\CommandDefinition;

/**
 * Commands stored in the database.
 *
 * The row holds the same array shape the config source accepts and goes through
 * the same parser, so a database command is indistinguishable from a config one
 * by the time anything executes it — including every guard in Command and
 * ArgvBuilder.
 *
 * This is the highest-privilege surface in the package: whoever can write these
 * rows can run whatever the PHP process can. Guard the editor with a strong
 * ability and treat write access as deploy access.
 */
final class DatabaseSource implements CommandSource
{
    public function __construct(
        private readonly int $defaultTimeout,
        private readonly ArrayDefinitionParser $parser = new ArrayDefinitionParser,
    ) {}

    /**
     * @return array<string, CommandDefinition>
     */
    public function definitions(): array
    {
        $definitions = [];

        foreach (CommandRecord::query()->where('is_enabled', true)->get() as $record) {
            $definitions[$record->key] = $this->parser->parse(
                $record->key,
                $record->definition,
                $this->defaultTimeout,
            );
        }

        return $definitions;
    }
}
