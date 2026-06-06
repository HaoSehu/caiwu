<?php

use App\Integrations\Mofang\MofangServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\UpstreamServiceProvider;

return [
    AppServiceProvider::class,
    MofangServiceProvider::class,
    UpstreamServiceProvider::class,
];
