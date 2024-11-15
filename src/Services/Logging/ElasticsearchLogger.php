<?php

namespace BPA\InfrastructureLib\Services\Logging;

use Elasticsearch\ClientBuilder;
use Monolog\Handler\ElasticsearchHandler;
use Monolog\Logger;

class ElasticsearchLogger
{
    public function __invoke(array $config)
    {
        $client = ClientBuilder::create()
            ->setHosts([
                [
                    'host' => $config['host'],
                    'port' => $config['port'],
                    'scheme' => 'http',
                ]
            ])
            ->build();

        $handler = new ElasticsearchHandler($client, [
            'index' => $config['index'],
            'type' => '_doc',
        ]);

        return new Logger('elasticsearch', [$handler]);
    }
}