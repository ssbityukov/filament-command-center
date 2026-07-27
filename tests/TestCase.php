<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Tests;

use Bityukov\CommandCenter\CommandCenterServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CommandCenterServiceProvider::class,
        ];
    }
}
