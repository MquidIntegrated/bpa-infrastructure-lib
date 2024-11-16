<?php

namespace BPA\InfrastructureLib\Events\Traits;

use BPA\InfrastructureLib\Events\EventManager;

trait PublishesEvents
{
    protected function publishEvent(string $event, array $payload)
    {
        app(EventManager::class)->dispatch($event, $payload);
    }

    public static function bootPublishesEvents()
    {
        // Helper method to automatically publish model events
        $publishModelEvent = function ($model, $eventName) {
            $model->publishEvent("model.{$eventName}", [
                'id' => $model->getKey(),
                'model' => get_class($model),
                'attributes' => $model->getAttributes(),
                'dirty' => $model->getDirty(),
                'timestamp' => now()->toIso8601String()
            ]);
        };

        // Standard model events
        static::created(function ($model) use ($publishModelEvent) {
            $publishModelEvent($model, 'created');
        });

        static::updated(function ($model) use ($publishModelEvent) {
            $publishModelEvent($model, 'updated');
        });

        static::deleted(function ($model) use ($publishModelEvent) {
            $publishModelEvent($model, 'deleted');
        });
    }
}