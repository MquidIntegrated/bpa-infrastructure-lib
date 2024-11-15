<?php

namespace BPA\InfrastructureLib\Services\EventBroker;

use RdKafka\Producer;
use RdKafka\Consumer;
use RdKafka\Conf;
use RdKafka\TopicConf;
use BPA\InfrastructureLib\Contracts\BaseBroadcaster;

class KafkaBroadcaster extends BaseBroadcaster
{
    private $conf;
    private $config;
    private $producer;
    private $consumer;

    public function __construct(Conf $conf, array $config)
    {
        $this->conf = $conf;
        $this->config = $config;
        $this->initializeProducer();
    }

    private function initializeProducer()
    {
        $this->producer = new Producer($this->conf);
        // Initialize Producer
        $this->producer->addBrokers($this->getBrokerString());
    }

    private function initializeConsumer()
    {
        $this->consumer = new Consumer($this->conf);
        $this->consumer->addBrokers($this->getBrokerString());
    }

    private function getBrokerString()
    {
        return collect($this->config['brokers'])->map(function ($broker) {
            return "{$broker['host']}:{$broker['port']}";
        })->implode(',');
    }

    public function broadcast($event, $payload)
    {
        $topic = $this->producer->newTopic($this->getTopicName($event));
        
        $topic->produce(RD_KAFKA_PARTITION_UA, 0, json_encode([
            'event' => $event,
            'payload' => $payload,
            'timestamp' => now()->timestamp
        ]));

        // Ensure message is sent
        $this->producer->flush(10000);
    }

    public function subscribe($callback)
    {
        $this->initializeConsumer();

        $topic = $this->consumer->newTopic('#');  // Subscribe to all topics
        $topic->consumeStart(0, RD_KAFKA_OFFSET_END);

        while (true) {
            $message = $topic->consume(0, 1000);
            
            if ($message === null) {
                continue;
            }

            switch ($message->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    $data = json_decode($message->payload, true);
                    $callback($data['event'], $data['payload']);
                    break;
                    
                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                    // Reached end of partition
                    break;
                    
                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    // Timeout
                    break;
                    
                default:
                    throw new \Exception($message->errstr(), $message->err);
            }
        }
    }

    private function getTopicName($event)
    {
        return str_replace('\\', '.', $event);
    }
}