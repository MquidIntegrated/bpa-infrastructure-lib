<?php
namespace BPA\InfrastructureLib\Providers;

use BPA\InfrastructureLib\Services\EventBroker\BroadcastListener;
use BPA\InfrastructureLib\Services\EventBroker\BroadcastManager;
use BPA\InfrastructureLib\Services\Monitoring\MetricsService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Debug\ExceptionHandler;
use BPA\InfrastructureLib\Services\ErrorHandling\ErrorHandlingService;
use Illuminate\Foundation\Application;
use BPA\InfrastructureLib\Events\EventManager;
use BPA\InfrastructureLib\Commands\ListenEventsCommand;

class InfrastructureLibServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->publishes([
            __DIR__ . '/../config/core_dependencies.php' => config_path('core_dependencies.php'),
        ]);

        $this->app->singleton(MetricsService::class, function ($app) {
            return new MetricsService(config('core_dependencies.metrics', []));
        });

        $this->mergeConfigFrom(
            __DIR__.'/../config/infrastructure.php', 'infrastructure'
        );

        // Register EventManager
        $this->app->singleton(EventManager::class, function ($app) {
            return new EventManager(config('infrastructure.events'));
        });

        // Register commands
        $this->commands([
            ListenEventsCommand::class
        ]);

        $this->setupMetricsCollectors();

        $this->mergeConfigFrom(__DIR__ . '/../../config/core_dependencies.php', 'core_dependencies');
    }

    private function setupMetricsCollectors()
    {
        $service = app(MetricsService::class);
        
        if (config('metrics.collect_db_metrics', true)) {
            $service->setupDatabaseMetrics();
        }
        
        if (config('metrics.collect_http_metrics', true)) {
            $service->setupHttpMetrics();
        }
        
        if (config('metrics.collect_cache_metrics', true)) {
            $service->setupCacheMetrics();
        }
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/infrastructure.php' => config_path('infrastructure.php'),
            ], 'infrastructure-config');
        }


        $this->publishes([
            __DIR__.'/../config/infrastructure.php' => config_path('infrastructure.php'),
        ], 'config');

        $this->bootEventListeners();
        $this->bootMonitoring();
        $this->integrateLogging();
    }

    private function bootEventListeners()
    {
        // Integrate with Laravel's event system

        $this->app['broadcast.listener']->listen();
        
        // Monitor performance metrics
        Event::listen('illuminate.query', function ($query, $bindings, $time) {
            $this->app['bpa.monitoring']->histogram(
                'query_duration_seconds',
                'Database query duration in seconds',
                ['query' => $query],
                [0.1, 0.25, 0.5, 1, 2.5, 5, 10]
            );
        });
    }

    private function bootMonitoring()
    {
        // Register basic application metrics
        $this->app->terminating(function () {
            $this->app['bpa.monitoring']->pushMetrics();
        });
        
        // Monitor request metrics
        $this->app->middleware->push(function ($request, $next) {
            $start = microtime(true);
            $response = $next($request);
            $duration = microtime(true) - $start;
            
            $this->app['bpa.monitoring']->histogram(
                'http_request_duration_seconds',
                'HTTP request duration in seconds',
                [
                    'method' => $request->method(),
                    'route' => $request->route()->getName() ?? 'unnamed',
                    'status' => $response->status()
                ]
            );
            
            return $response;
        });
    }

    private function integrateLogging()
    {
        // Extend Laravel's logging to include our logging service
        Log::extend('bpa', function (Application $app, array $config) {
            return $app['bpa.logging'];
        });
        
        // Set as default channel
        config(['logging.default' => 'bpa']);
    }
}