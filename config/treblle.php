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
    'exclude' => explode(',', env('TREBLLE_EXCLUDE', 'local')),
];
