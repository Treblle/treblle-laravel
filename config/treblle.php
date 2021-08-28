<?php

return [

    /*
     * A valid Treblle API key. You can get started for FREE by visiting https://treblle.com/
     */
    'api_key' => env('TREBLLE_API_KEY', ''),

    /*
     * A valid Treblle project ID. Create your first project on https://treblle.com/
     */
    'project_id' => env('TREBLLE_PROJECT_ID', ''),

    /*
     * Define which environments should Treblle ignore and not monitor
     */
    'ignored_enviroments' => env('TREBLLE_IGNORED_ENV', 'local'),

    /*
     * Define which fields should be masked before leaving the server
     */
    'masked_fields' => env('TREBLLE_MASKED_FIELDS', ''),

    /**
     * If true, for servers that support FastCGI protocol, this will trigger the "termination" call of the middleware. 
     * For servers that do not supports this protocol, set it to false to still get your payload to be sent on your Treblle dashboard.
     * 
     * @see https://laravel.com/docs/8.x/middleware#terminable-middleware
     */
    'use_fastcgi' => env('TREBLLE_USE_FASTCGI', true),
];
