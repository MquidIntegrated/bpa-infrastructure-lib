<?php

namespace BPA\InfrastructureLib\Commands;

use Illuminate\Console\Command;
use BPA\InfrastructureLib\Events\EventManager;

class ListenEventsCommand extends Command
{
    protected $signature = 'events:listen';
    protected $description = 'Listen for events from message queue';

    protected $eventManager;

    public function __construct(EventManager $eventManager)
    {
        parent::__construct();
        $this->eventManager = $eventManager;
    }

    public function handle()
    {
        $this->info('Starting event listener...');

        // Register event handlers from config
        foreach (config('infrastructure.handlers', []) as $event => $handler) {
            $this->eventManager->registerHandler($event, $handler);
        }

        // Start consuming events
        $this->eventManager->listen();
    }
}