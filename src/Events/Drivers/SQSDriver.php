<?php

namespace BPA\InfrastructureLib\Events\Drivers;

use Aws\Sqs\SqsClient;
use Aws\Exception\AwsException;

class SQSDriver implements DriverInterface
{
    protected $client;
    protected $config;
    protected $queueUrls = [];
    protected $connected = false;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function connect(): void
    {
        $config = [
            'version' => 'latest',
            'region'  => $this->config['region']
        ];

        if (isset($this->config['key'])) {
            $config['credentials'] = [
                'key'    => $this->config['key'],
                'secret' => $this->config['secret']
            ];
        }

        if (isset($this->config['role_arn'])) {
            $config['credentials'] = new \Aws\Credentials\AssumeRoleCredentialProvider([
                'client' => new \Aws\Sts\StsClient($config),
                'role_arn' => $this->config['role_arn'],
                'role_session_name' => 'infrastructure-lib-session'
            ]);
        }

        if (isset($this->config['endpoint'])) {
            $config['endpoint'] = $this->config['endpoint'];
        }

        $this->client = new SqsClient($config);
        $this->connected = true;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function subscribe(array $events): void
    {
        foreach ($events as $event) {
            try {
                $result = $this->client->getQueueUrl(['QueueName' => $event]);
                $this->queueUrls[$event] = $result->get('QueueUrl');
            } catch (AwsException $e) {
                if ($e->getAwsErrorCode() === 'AWS.SimpleQueueService.NonExistentQueue' 
                    && ($this->config['auto_create_queue'] ?? false)) {
                    $result = $this->client->createQueue([
                        'QueueName' => $event,
                        'Attributes' => [
                            'VisibilityTimeout' => $this->config['visibility_timeout'] ?? 30,
                            'MessageRetentionPeriod' => $this->config['message_retention_period'] ?? 345600
                        ]
                    ]);
                    $this->queueUrls[$event] = $result->get('QueueUrl');
                } else {
                    throw $e;
                }
            }
        }
    }

    public function consume(callable $callback): void
    {
        while (true) {
            foreach ($this->queueUrls as $event => $queueUrl) {
                try {
                    $result = $this->client->receiveMessage([
                        'QueueUrl' => $queueUrl,
                        'MaxNumberOfMessages' => $this->config['batch_size'] ?? 10,
                        'WaitTimeSeconds' => $this->config['wait_time'] ?? 20,
                        'VisibilityTimeout' => $this->config['visibility_timeout'] ?? 30
                    ]);

                    if ($messages = $result->get('Messages')) {
                        foreach ($messages as $message) {
                            try {
                                $payload = json_decode($message['Body'], true);
                                $callback($event, $payload);

                                $this->client->deleteMessage([
                                    'QueueUrl' => $queueUrl,
                                    'ReceiptHandle' => $message['ReceiptHandle']
                                ]);
                            } catch (\Exception $e) {
                                // Change visibility timeout to retry later
                                $this->client->changeMessageVisibility([
                                    'QueueUrl' => $queueUrl,
                                    'ReceiptHandle' => $message['ReceiptHandle'],
                                    'VisibilityTimeout' => $this->config['retry_delay'] ?? 60
                                ]);
                                throw $e;
                            }
                        }
                    }
                } catch (AwsException $e) {
                    if ($e->getAwsErrorCode() === 'RequestThrottled') {
                        sleep($this->config['throttle_delay'] ?? 2);
                    } else {
                        throw $e;
                    }
                }
            }
        }
    }

    public function dispatch(string $event, array $payload): void
    {
        if (!isset($this->queueUrls[$event])) {
            $result = $this->client->getQueueUrl(['QueueName' => $event]);
            $this->queueUrls[$event] = $result->get('QueueUrl');
        }

        $messageParams = [
            'QueueUrl' => $this->queueUrls[$event],
            'MessageBody' => json_encode($payload)
        ];

        if (isset($this->config['message_group_id'])) {
            $messageParams['MessageGroupId'] = $this->config['message_group_id'];
        }

        if (isset($this->config['deduplication_id'])) {
            $messageParams['MessageDeduplicationId'] = uniqid($this->config['deduplication_id'] . '_');
        }

        $this->client->sendMessage($messageParams);
    }
}