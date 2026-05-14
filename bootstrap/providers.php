<?php

$providers = [
    App\Common\Providers\CommonServiceProvider::class,
    App\Providers\AppServiceProvider::class,
    App\Providers\TelescopeServiceProvider::class,
    App\Providers\TenancyServiceProvider::class,
];

// Dev-only packages (dont-discover in composer.json), register manually when available
if (class_exists(\Laravel\Boost\BoostServiceProvider::class)) {
    $providers[] = \Laravel\Boost\BoostServiceProvider::class;
}
if (class_exists(\Laravel\Mcp\Server\McpServiceProvider::class)) {
    $providers[] = \Laravel\Mcp\Server\McpServiceProvider::class;
}

return $providers;
