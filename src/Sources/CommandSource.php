<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Sources;

use Bityukov\CommandCenter\Definitions\CommandDefinition;

interface CommandSource
{
    /**
     * @return array<string, CommandDefinition> keyed by command key
     */
    public function definitions(): array;
}
