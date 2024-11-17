<?php

namespace BPA\InfrastructureLib\Listeners;

use BPA\InfrastructureLib\Services\Monitoring\MetricsService;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;

class CacheMissListener
{
    private $metricsService;

    public function __construct(MetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
    }

    public function handle(CacheMissed $event)
    {
        $this->metricsService->recordCacheMiss();
    }
}