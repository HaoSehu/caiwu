<?php

use App\Integrations\Mofang\MofangServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\IntegrationServiceProvider;
use App\Providers\UpstreamServiceProvider;

return [
    AppServiceProvider::class,
    IntegrationServiceProvider::class,
    MofangServiceProvider::class,
    UpstreamServiceProvider::class,
];
