<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Definitions;

enum CommandType: string
{
    case Artisan = 'artisan';
    case Shell = 'shell';
}
