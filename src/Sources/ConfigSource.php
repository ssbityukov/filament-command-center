<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Sources;

use Bityukov\CommandCenter\Definitions\CommandDefinition;

final class ConfigSource implements CommandSource
{
    /**
     * @param  array<string, array<string, mixed>>  $commands
     */
    public function __construct(
        private readonly array $commands,
        private readonly int $defaultTimeout,
        private readonly ArrayDefinitionParser $parser = new ArrayDefinitionParser,
    ) {}

    /**
     * @return array<string, CommandDefinition>
     */
    public function definitions(): array
    {
        $definitions = [];

        foreach ($this->commands as $key => $config) {
            $definitions[(string) $key] = $this->parser->parse((string) $key, $config, $this->defaultTimeout);
        }

        return $definitions;
    }
}
