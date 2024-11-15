<?php

namespace BPA\InfrastructureLib\Facades;

use Illuminate\Support\Facades\Facade;

class BPAEventBroker extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'bpa.event-broker';
    }
}
