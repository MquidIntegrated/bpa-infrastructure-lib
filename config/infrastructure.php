<?php

return [
    'events' => [
        'driver' => env('EVENT_DRIVER', 'rabbitmq'),

        'rabbitmq' => [
            'host' => env('RABBITMQ_HOST', 'localhost'),
            'port' => env('RABBITMQ_PORT', 5672),
            'user' => env('RABBITMQ_USER', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'queue' => env('RABBITMQ_QUEUE', 'default'),
            'exchange' => env('RABBITMQ_EXCHANGE', 'events'),
        ],

        'kafka' => [
            'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),
            'group_id' => env('KAFKA_GROUP_ID', 'my-consumer-group'),
            'timeout_ms' => env('KAFKA_TIMEOUT_MS', 12000),
            'flush_timeout_ms' => env('KAFKA_FLUSH_TIMEOUT_MS', 10000),
            'security_protocol' => env('KAFKA_SECURITY_PROTOCOL'),
            'sasl_mechanisms' => env('KAFKA_SASL_MECHANISMS'),
            'sasl_username' => env('KAFKA_USERNAME'),
            'sasl_password' => env('KAFKA_PASSWORD'),
            'auto_commit' => env('KAFKA_AUTO_COMMIT', true),
        ],

        'activemq' => [
            'host' => explode(',', env('ACTIVEMQ_HOSTS', 'localhost')),
            'port' => env('ACTIVEMQ_PORT', 61613),
            'user' => env('ACTIVEMQ_USER', 'admin'),
            'password' => env('ACTIVEMQ_PASSWORD', 'admin'),
            'destination_type' => env('ACTIVEMQ_DESTINATION_TYPE', 'queue'), // or 'topic'
            'durable_subscription' => env('ACTIVEMQ_DURABLE_SUBSCRIPTION', true),
            'client_id' => env('ACTIVEMQ_CLIENT_ID', env('APP_NAME')),
            'prefetch_size' => env('ACTIVEMQ_PREFETCH_SIZE', 1),
            'delivery_mode' => env('ACTIVEMQ_DELIVERY_MODE', 2), // 1 = non-persistent, 2 = persistent
        ],

        'sqs' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'role_arn' => env('AWS_ROLE_ARN'),
            'endpoint' => env('SQS_ENDPOINT'), // for localstack
            'batch_size' => env('SQS_BATCH_SIZE', 10),
            'wait_time' => env('SQS_WAIT_TIME', 20),
            'visibility_timeout' => env('SQS_VISIBILITY_TIMEOUT', 30),
            'message_retention_period' => env('SQS_MESSAGE_RETENTION_PERIOD', 345600),
            'auto_create_queue' => env('SQS_AUTO_CREATE_QUEUE', false),
            'retry_delay' => env('SQS_RETRY_DELAY', 60),
            'throttle_delay' => env('SQS_THROTTLE_DELAY', 2),
            // For FIFO queues
            'message_group_id' => env('SQS_MESSAGE_GROUP_ID'),
            'deduplication_id' => env('SQS_DEDUPLICATION_ID'),
        ],
    ],

    'retry' => [
        'max_attempts' => env('EVENT_RETRY_MAX_ATTEMPTS', 3),
        'delay' => env('EVENT_RETRY_DELAY', 5),
        'multiplier' => env('EVENT_RETRY_MULTIPLIER', 2),
    ],

    'handlers' => [
        // Register your event handlers here
        // 'user.created' => \App\Events\Handlers\UserCreatedHandler::class,
        // 'user.updated' => \App\Events\Handlers\UserUpdatedHandler::class,
        // 'user.deleted' => \App\Events\Handlers\UserDeletedHandler::class,
    ],
];