<?php

namespace BPA\InfrastructureLib\Events\Drivers;

use RdKafka\KafkaConsumer;
use RdKafka\Producer;
use RdKafka\Conf;

class KafkaDriver implements DriverInterface
{
    protected $consumer;
    protected $producer;
    protected $config;
    protected $connected = false;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function connect(): void
    {
        // Create configuration
        $conf = new Conf();
        $conf->set('bootstrap.servers', $this->config['brokers']);
        $conf->set('group.id', $this->config['group_id']);
        $conf->set('auto.offset.reset', 'earliest');

        // Set security configuration if provided
        if (isset($this->config['security_protocol'])) {
            $conf->set('security.protocol', $this->config['security_protocol']);
            $conf->set('sasl.mechanisms', $this->config['sasl_mechanisms']);
            $conf->set('sasl.username', $this->config['sasl_username']);
            $conf->set('sasl.password', $this->config['sasl_password']);
        }

        // Create consumer
        $this->consumer = new KafkaConsumer($conf);

        // Create producer with separate configuration
        $producerConf = new Conf();
        $producerConf->set('bootstrap.servers', $this->config['brokers']);
        
        if (isset($this->config['security_protocol'])) {
            $producerConf->set('security.protocol', $this->config['security_protocol']);
            $producerConf->set('sasl.mechanisms', $this->config['sasl_mechanisms']);
            $producerConf->set('sasl.username', $this->config['sasl_username']);
            $producerConf->set('sasl.password', $this->config['sasl_password']);
        }

        $this->producer = new Producer($producerConf);
        $this->connected = true;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function subscribe(array $events): void
    {
        $this->consumer->subscribe($events);
    }

    public function consume(callable $callback): void
    {
        while (true) {
            $message = $this->consumer->consume($this->config['timeout_ms'] ?? 120000);
            
            switch ($message->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    $callback(
                        $message->topic_name,
                        json_decode($message->payload, true)
                    );
                    break;

                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    // Handle timeouts and partition EOF
                    continue 2;

                default:
                    throw new \Exception("Kafka Error: {$message->errstr()}", $message->err);
            }
        }
    }

    public function dispatch(string $event, array $payload): void
    {
        $topic = $this->producer->newTopic($event);
        $topic->produce(RD_KAFKA_PARTITION_UA, 0, json_encode($payload));
        $this->producer->flush($this->config['flush_timeout_ms'] ?? 10000);
    }
}