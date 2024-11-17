<?php

namespace BPA\InfrastructureLib\Listeners;

use BPA\InfrastructureLib\Services\Monitoring\MetricsService;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;

class CacheHitListener
{
    private $metricsService;

    public function __construct(MetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
    }

    public function handle(CacheHit $event)
    {
        $this->metricsService->recordCacheHit();
    }
}
