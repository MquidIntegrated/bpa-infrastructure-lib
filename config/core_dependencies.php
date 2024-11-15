<?php

return [
    'metrics' => [
        'storage' => [
            'host' => env('METRICS_REDIS_HOST', '127.0.0.1'),
            'port' => env('METRICS_REDIS_PORT', 6379),
            'password' => env('METRICS_REDIS_PASSWORD'),
        ],
        'namespace' => env('APP_NAME', 'app'),
        'collect_db_metrics' => true,
        'collect_http_metrics' => true,
        'collect_cache_metrics' => true,
        'buckets' => [0.1, 0.3, 0.5, 0.7, 1, 2, 3, 5, 7, 10],
    ],
    'event_broker' => [
        'default' => env('EVENT_BROKER_DRIVER', 'rabbitmq'),
        
        'connections' => [
            'rabbitmq' => [
                'host' => env('RABBITMQ_HOST', 'localhost'),
                'port' => env('RABBITMQ_PORT', 5672),
                'user' => env('RABBITMQ_USER', 'guest'),
                'password' => env('RABBITMQ_PASSWORD', 'guest'),
                'vhost' => env('RABBITMQ_VHOST', '/'),
            ],
            
            'kafka' => [
                'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),
                'security_protocol' => env('KAFKA_SECURITY_PROTOCOL', 'PLAINTEXT'),
                'sasl' => [
                    'mechanism' => env('KAFKA_SASL_MECHANISM'),
                    'username' => env('KAFKA_SASL_USERNAME'),
                    'password' => env('KAFKA_SASL_PASSWORD'),
                ],
            ],
            
            'sqs' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
                'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
                'prefix' => env('SQS_PREFIX', ''),
            ],
            
            'activemq' => [
                'host' => env('ACTIVEMQ_HOST', 'localhost'),
                'port' => env('ACTIVEMQ_PORT', 61613),
                'username' => env('ACTIVEMQ_USERNAME'),
                'password' => env('ACTIVEMQ_PASSWORD'),
            ],
        ],
        
        'events' => [
            // Event to exchange/topic mappings
            'user.registered' => [
                'exchange' => 'user_events',
                'routing_key' => 'user.registered',
            ],
            'order.created' => [
                'exchange' => 'order_events',
                'routing_key' => 'order.created',
            ],
            // Add more event mappings as needed
        ],
    ],
    'logging' => [
        'elk' => [
            'host' => env('ELK_HOST', 'localhost'),
            'port' => env('ELK_PORT', 9200),
        ],
        'prometheus' => [
            'host' => env('PROMETHEUS_HOST', 'localhost'),
            'port' => env('PROMETHEUS_PORT', 9090),
        ],
    ],
    'service_discovery' => [
        'provider' => env('SERVICE_DISCOVERY_PROVIDER', 'consul'),
        'host' => env('SERVICE_DISCOVERY_HOST', 'localhost'),
        'port' => env('SERVICE_DISCOVERY_PORT', 8500),
    ],
    'event_broker' => [
        'provider' => env('EVENT_BROKER_PROVIDER', 'rabbitmq'),
        'host' => env('EVENT_BROKER_HOST', 'localhost'),
        'port' => env('EVENT_BROKER_PORT', 5672),
        'user' => env('EVENT_BROKER_USER', 'guest'),
        'password' => env('EVENT_BROKER_PASSWORD', 'guest'),
    ],
    'cache' => [
        'redis' => [
            'host' => env('REDIS_HOST', 'localhost'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
        ],
    ],
    'data_warehouse' => [
        'provider' => env('DATA_WAREHOUSE_PROVIDER', 'bigquery'),
        'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
        'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS'),
    ],
];
