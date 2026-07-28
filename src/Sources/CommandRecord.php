<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Sources;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key
 * @property bool $is_enabled
 * @property array<string, mixed> $definition
 */
class CommandRecord extends Model
{
    protected $table = 'command_center_commands';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'is_enabled' => 'boolean',
        ];
    }
}
