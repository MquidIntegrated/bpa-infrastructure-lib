<?php

namespace BPA\InfrastructureLib\Services\Monitoring;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class MetricsService
{
    private CollectorRegistry $registry;
    private array $counters = [];
    private array $gauges = [];
    private array $histograms = [];
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->setupStorage();
        $this->registry = CollectorRegistry::getDefault();
        $this->initializeMetrics();
    }

    private function setupStorage()
    {
        $storageConfig = $this->config['storage'] ?? [];
        Redis::setDefaultOptions([
            'host' => $storageConfig['host'] ?? env('REDIS_HOST', '127.0.0.1'),
            'port' => $storageConfig['port'] ?? env('REDIS_PORT', 6379),
            'password' => $storageConfig['password'] ?? env('REDIS_PASSWORD', null),
        ]);
    }

    private function initializeMetrics()
    {
        $namespace = $this->config['namespace'] ?? 'app';
        $buckets = $this->config['buckets'] ?? [0.1, 0.3, 0.5, 0.7, 1, 2, 3, 5, 7, 10];

        $this->initializeHttpMetrics($namespace, $buckets);
        $this->initializeDatabaseMetrics($namespace, $buckets);
        $this->initializeCacheMetrics($namespace);
        $this->initializeQueueMetrics($namespace);
        $this->initializeMemoryMetrics($namespace);
    }

    private function initializeHttpMetrics(string $namespace, array $buckets)
    {
        $this->counters['http_requests_total'] = $this->registry->getOrRegisterCounter(
            $namespace,
            'http_requests_total',
            'Total HTTP requests',
            ['method', 'route', 'status']
        );

        $this->histograms['http_request_duration'] = $this->registry->getOrRegisterHistogram(
            $namespace,
            'http_request_duration_seconds',
            'HTTP request duration',
            ['route'],
            $buckets
        );
    }

    private function initializeDatabaseMetrics(string $namespace, array $buckets)
    {
        $this->counters['db_queries_total'] = $this->registry->getOrRegisterCounter(
            $namespace,
            'db_queries_total',
            'Total database queries'
        );

        $this->histograms['db_query_duration'] = $this->registry->getOrRegisterHistogram(
            $namespace,
            'db_query_duration_seconds',
            'Database query duration',
            [],
            $buckets
        );
    }

    private function initializeQueueMetrics(string $namespace)
    {
        $this->gauges['queue_size'] = $this->registry->getOrRegisterGauge(
            $namespace,
            'queue_size',
            'Queue size',
            ['queue']
        );
    }

    private function initializeMemoryMetrics(string $namespace)
    {
        $this->gauges['memory_usage'] = $this->registry->getOrRegisterGauge(
            $namespace,
            'memory_usage_bytes',
            'Memory usage in bytes'
        );
    }

    public function setupMetricCollectors()
    {
        if ($this->config['collect_db_metrics'] ?? true) {
            $this->setupDatabaseMetrics();
        }

        if ($this->config['collect_http_metrics'] ?? true) {
            $this->setupHttpMetrics();
        }

        if ($this->config['collect_cache_metrics'] ?? true) {
            $this->setupCacheMetrics();
        }
    }

    public function setupDatabaseMetrics()
    {
        DB::listen(function ($query) {
            $this->recordDbQuery($query->time / 1000);
        });
    }

    public function setupHttpMetrics()
    {
        app('router')->matched(function ($route, $request) {
            $start = microtime(true);

            app()->terminating(function () use ($start, $request, $route) {
                $duration = microtime(true) - $start;
                $this->recordHttpRequest(
                    $request->method(),
                    $route->uri(),
                    http_response_code(),
                    $duration
                );
            });
        });
    }

    // Alternative approach using event subscription
    private function initializeCacheMetrics(string $namespace)
    {
        $this->counters['cache_hits'] = $this->registry->getOrRegisterCounter(
            $namespace,
            'cache_hits_total',
            'Cache hits'
        );

        $this->counters['cache_misses'] = $this->registry->getOrRegisterCounter(
            $namespace,
            'cache_misses_total',
            'Cache misses'
        );
    }

    public function recordCacheHit()
    {
        if (isset($this->counters['cache_hits'])) {
            $this->counters['cache_hits']->inc();
        }
    }

    public function recordCacheMiss()
    {
        if (isset($this->counters['cache_misses'])) {
            $this->counters['cache_misses']->inc();
        }
    }

    public function setupCacheMetrics()
    {
        if (!class_exists('Illuminate\Support\Facades\Cache')) {
            return;
        }

        try {
            // Update the event listeners to use event objects
            Cache::missing(function (CacheMissed $event) {
                $this->recordCacheMiss();
            });

            Cache::hit(function (CacheHit $event) {
                $this->recordCacheHit();
            });
        } catch (\Exception $e) {
            report($e);
        }
    }

    public function recordHttpRequest($method, $route, $status, $duration)
    {
        $this->counters['http_requests_total']->inc(['method' => $method, 'route' => $route, 'status' => $status]);
        $this->histograms['http_request_duration']->observe($duration, ['route' => $route]);
    }

    public function recordDbQuery($duration)
    {
        $this->counters['db_queries_total']->inc();
        $this->histograms['db_query_duration']->observe($duration);
    }

    public function updateQueueSize($queue, $size)
    {
        $this->gauges['queue_size']->set($size, ['queue' => $queue]);
    }

    public function updateMemoryUsage()
    {
        $this->gauges['memory_usage']->set(memory_get_usage(true));
    }

    /**
     * Generic method to record a histogram metric
     *
     * @param string $name
     * @param string $help
     * @param array $labels
     * @param float $value
     * @return void
     */
    public function histogram(string $name, string $help, array $labels = [], float $value)
    {
        $namespace = $this->config['namespace'] ?? 'app';
        $buckets = $this->config['buckets'] ?? [0.1, 0.3, 0.5, 0.7, 1, 2, 3, 5, 7, 10];

        if (!isset($this->histograms[$name])) {
            $this->histograms[$name] = $this->registry->getOrRegisterHistogram(
                $namespace,
                $name,
                $help,
                array_keys($labels),
                $buckets
            );
        }

        $this->histograms[$name]->observe($value, $labels);
    }

}