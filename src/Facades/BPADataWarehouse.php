<?php

namespace BPA\InfrastructureLib\Facades;

use Illuminate\Support\Facades\Facade;

class BPADataWarehouse extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'bpa.data-warehouse';
    }
}