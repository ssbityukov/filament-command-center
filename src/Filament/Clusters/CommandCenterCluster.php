<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Filament\Clusters;

use Filament\Clusters\Cluster;

class CommandCenterCluster extends Cluster
{
    protected static ?string $slug = 'command-center';

    protected static ?string $navigationLabel = 'Command Center';

    protected static ?string $clusterBreadcrumb = 'Command Center';
}
