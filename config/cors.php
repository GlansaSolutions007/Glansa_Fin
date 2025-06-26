<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // 'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['http://localhost:3000'], 

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Authorization'],

    'max_age' => 0,

    'supports_credentials' => true, 


];


    // 'paths' => ['*'],  // Allow all routes, or specify the routes if needed
    // 'allowed_methods' => ['*'],  // Allow all HTTP methods
    // 'allowed_origins' => [
    //     'http://localhost:3000', // Explicitly allow your React frontend
    //     'http://127.0.0.1:3000', // Allow for both `localhost` and `127.0.0.1` origins
    // ],
    // 'allowed_headers' => ['*'],  // Allow all headers
    // 'supports_credentials' => true,  // Allow credentials (like cookies, tokens)
    // 'allowed_origins_patterns' => [],
    // 'exposed_headers' => [],
    // 'max_age' => 0,