<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A second actor model behind its own guard.
 *
 * Ids collide with TestUser on purpose: id 1 is a different person in each
 * table, which is what makes resolving through the wrong guard observable.
 */
class TestAdmin extends Authenticatable
{
    protected $table = 'admins';

    protected $guarded = [];

    public $timestamps = false;
}
