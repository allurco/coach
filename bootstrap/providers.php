<?php

use App\Agent\AgentServiceProvider;
use App\Packs\PackServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    PackServiceProvider::class,
    AgentServiceProvider::class,
];
