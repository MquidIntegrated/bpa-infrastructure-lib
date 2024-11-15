<?php

namespace BPA\InfrastructureLib\Services\EventBroker;

use Aws\Sqs\SqsClient;
use BPA\InfrastructureLib\Contracts\BaseBroadcaster;

class SQSBroadcaster extends BaseBroadcaster
{
    private $client;
    private $queueUrl;

    public function __construct(SqsClient $client, $queueUrl)
    {
        $this->client = $client;
        $this->queueUrl = $queueUrl;
    }

    public function broadcast($event, $payload)
    {
        $this->client->sendMessage([
            'QueueUrl' => $this->queueUrl,
            'MessageBody' => json_encode([
                'event' => $event,
                'payload' => $payload
            ])
        ]);
    }

    public function subscribe($callback)
    {
        while (true) {
            $result = $this->client->receiveMessage([
                'QueueUrl' => $this->queueUrl,
                'WaitTimeSeconds' => 20
            ]);

            if (!empty($result['Messages'])) {
                foreach ($result['Messages'] as $message) {
                    $body = json_decode($message['Body'], true);
                    $callback($body['event'], $body['payload']);
                    
                    $this->client->deleteMessage([
                        'QueueUrl' => $this->queueUrl,
                        'ReceiptHandle' => $message['ReceiptHandle']
                    ]);
                }
            }
        }
    }
}
