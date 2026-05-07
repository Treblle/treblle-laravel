<?php

declare(strict_types=1);

return [
    /*
     * Enable or disable Treblle monitoring
     */
    'enable' => env('TREBLLE_ENABLE', true),

    /*
     * The Treblle ingress endpoint. Override via TREBLLE_API_URL for custom deployments.
     */
    'url' => env('TREBLLE_API_URL', 'https://ingress.treblle.com'),

    /*
     * Your Treblle SDK Token. You can get started for FREE by visiting https://treblle.com/
     * In v5: Previously called 'api_key'
     */
    'sdk_token' => env('TREBLLE_SDK_TOKEN'),

    /*
     * Your Treblle API Key. Create your first project on https://treblle.com/
     * In v5: Previously called 'project_id'
     */
    'api_key' => env('TREBLLE_API_KEY'),

    /*
     * Define which environments should Treblle ignore and not monitor
     */
    'ignored_environments' => env('TREBLLE_IGNORED_ENV', 'dev,test,testing'),

    /*
     * Static metadata included in every request payload.
     * Per-request metadata can be added by setting the 'treblle_metadata' request attribute:
     *   $request->attributes->set('treblle_metadata', ['key' => 'value']);
     * Per-request values are merged over these static values.
     */
    'metadata' => [],

    /*
     * HTTP methods that Treblle should never monitor.
     * OPTIONS and HEAD are excluded by default — they are high-volume noise that
     * carry no request body and pollute the Treblle dashboard.
     *
     * Add any other methods you want to skip, e.g. 'PATCH', 'DELETE'.
     */
    'ignored_methods' => ['HEAD', 'OPTIONS'],

    /*
     * Define which fields should be masked before leaving the server
     */
    'masked_fields' => [
        'password',
        'pwd',
        'secret',
        'password_confirmation',
        'cc',
        'card_number',
        'ccv',
        'ssn',
        'credit_score',
        'api_key',
    ],

    /*
     * Define which headers should be excluded from logging
     */
    'excluded_headers' => [],

    /*
     * Should be used in development mode only.
     * Enable Debug mode, will throw errors on apis.
     */
    'debug' => env('TREBLLE_DEBUG_MODE', false),

    /*
     * Queue Configuration
     *
     * Enable asynchronous data transmission using Laravel queues.
     * When enabled, Treblle data will be sent via jobs instead of synchronously.
     *
     * Supported connections: redis, sqs, beanstalkd, database (with proper indexes)
     * Not recommended: sync, file (slow and unreliable)
     */
    'queue' => [
        /*
         * Enable queue-based data transmission
         */
        'enabled' => env('TREBLLE_QUEUE_ENABLED', false),

        /*
         * Queue connection to use (must be configured in config/queue.php)
         * If null, uses the default queue connection
         * Recommended: redis, sqs, beanstalkd
         */
        'connection' => env('TREBLLE_QUEUE_CONNECTION', 'redis'),

        /*
         * Queue name to dispatch jobs to
         * If null, uses the default queue for the connection
         */
        'queue' => env('TREBLLE_QUEUE_NAME', 'default'),
    ],
];
