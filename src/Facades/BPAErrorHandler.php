<?php

namespace BPA\InfrastructureLib\Facades;

use Illuminate\Support\Facades\Facade;

class BPAErrorHandler extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'bpa.error-handling';
    }
}
