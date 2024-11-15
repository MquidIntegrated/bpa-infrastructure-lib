<?php

namespace BPA\InfrastructureLib\Facades;

use Illuminate\Support\Facades\Facade;

class BPAServiceDiscovery extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'bpa.service-discovery';
    }
}
