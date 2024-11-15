<?php

// src/Facades/BPACache.php
namespace BPA\InfrastructureLib\Facades;

use Illuminate\Support\Facades\Facade;

class BPACache extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'bpa.cache';
    }
}