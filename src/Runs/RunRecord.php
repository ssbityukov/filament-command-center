<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Runs;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $command_key
 * @property string $label
 * @property string|null $user_id
 * @property array<string, mixed> $input
 * @property array<int, string> $argv
 * @property string $state
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property int|null $duration_ms
 * @property int|null $exit_code
 * @property string|null $output
 * @property int|null $progress
 * @property string|null $error
 */
class RunRecord extends Model
{
    protected $table = 'command_center_runs';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    /**
     * Microseconds are part of the format, not just the column.
     *
     * Eloquent serialises dates with this format regardless of driver, and the
     * default drops sub-second precision — which left two runs started in the
     * same second with no defined order and made history sort at random.
     */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input' => 'array',
            'argv' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
