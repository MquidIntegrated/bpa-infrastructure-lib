<?php

namespace BPA\InfrastructureLib\Facades;

use Illuminate\Support\Facades\Facade;

class BPAMonitoring extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'bpa.monitoring';
    }
}
