<?php

namespace BPA\InfrastructureLib\Services\EventBroker;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Aws\Sqs\SqsClient;
use Stomp\Client as StompClient;

class BroadcastManager
{
    private $app;
    private $drivers = [];

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function driver($name = null)
    {
        $name = $name ?: $this->getDefaultDriver();
        
        return $this->drivers[$name] = $this->get($name);
    }

    protected function get($name)
    {
        return $this->drivers[$name] ?? $this->resolve($name);
    }

    protected function resolve($name)
    {
        $config = $this->getConfig($name);
        $method = 'create' . ucfirst($name) . 'Driver';

        return $this->$method($config);
    }

    protected function createRabbitmqDriver($config)
    {
        $connection = new AMQPStreamConnection(
            $config['host'],
            $config['port'],
            $config['user'],
            $config['password']
        );
        
        return new RabbitMQBroadcaster($connection);
    }

    protected function createSqsDriver($config)
    {
        $client = new SqsClient([
            'version' => 'latest',
            'region' => $config['region'],
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
        ]);
        
        return new SQSBroadcaster($client, $config['queue']);
    }

    protected function createActivemqDriver($config)
    {
        $client = new StompClient("tcp://{$config['host']}:{$config['port']}");
        $client->connect($config['user'], $config['password']);
        
        return new ActiveMQBroadcaster($client);
    }

    public function getDefaultDriver()
    {
        return 'rabbitmq';
    }

    protected function getConfig($name)
    {
        return config("broadcasting.connections.{$name}");
    }

    protected function createKafkaDriver($config)
    {
        $conf = new Conf();
        
        // Producer configuration
        $conf->set('metadata.broker.list', $this->getKafkaBrokerString($config));
        
        if (isset($config['sasl'])) {
            $this->configureSasl($conf, $config['sasl']);
        }

        if (isset($config['ssl'])) {
            $this->configureSsl($conf, $config['ssl']);
        }

        // Configure group ID for consumer
        $conf->set('group.id', $config['group_id'] ?? 'laravel_app');
        $conf->set('auto.offset.reset', 'latest');

        return new KafkaBroadcaster($conf, $config);
    }

    private function getKafkaBrokerString($config)
    {
        return collect($config['brokers'])->map(function ($broker) {
            return "{$broker['host']}:{$broker['port']}";
        })->implode(',');
    }

    private function configureSasl($conf, $saslConfig)
    {
        $conf->set('security.protocol', 'SASL_SSL');
        $conf->set('sasl.mechanisms', $saslConfig['mechanism']);
        $conf->set('sasl.username', $saslConfig['username']);
        $conf->set('sasl.password', $saslConfig['password']);
    }

    private function configureSsl($conf, $sslConfig)
    {
        if (isset($sslConfig['ca_location'])) {
            $conf->set('ssl.ca.location', $sslConfig['ca_location']);
        }
        if (isset($sslConfig['certificate_location'])) {
            $conf->set('ssl.certificate.location', $sslConfig['certificate_location']);
        }
        if (isset($sslConfig['key_location'])) {
            $conf->set('ssl.key.location', $sslConfig['key_location']);
        }
    }
}