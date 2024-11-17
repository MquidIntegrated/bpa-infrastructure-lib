<?php

namespace BPA\InfrastructureLib\Services\Logging;

use Monolog\Handler\ElasticsearchHandler;
use Monolog\Logger;
use Elasticsearch\ClientBuilder;

class ElasticsearchLogger
{
    public function __invoke(array $config)
    {
        // Create an Elasticsearch client
        $client = ClientBuilder::create()
            ->setHosts([sprintf('%s:%s', $config['host'], $config['port'])])
            ->build();

        // Create a handler for Elasticsearch
        $handler = new ElasticsearchHandler($client, [
            'index' => $config['index'],
            'type' => '_doc', // Type is deprecated in newer Elasticsearch versions
        ]);

        // Create the logger
        return new Logger('elasticsearch', [$handler]);
    }
}