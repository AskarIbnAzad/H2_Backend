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

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];

// for server

//return [
//    /*
//    |--------------------------------------------------------------------------
//    | Cross-Origin Resource Sharing (CORS) Configuration
//    |--------------------------------------------------------------------------
//    |
//    | Here you may configure your settings for cross-origin resource sharing
//    | or "CORS". This determines what cross-origin operations may execute
//    | in web browsers. You are free to adjust these settings as needed.
//    |
//    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
//    |
//    */
//
//    'paths' => ['api/*', 'sanctum/csrf-cookie', 'final-article-list-main'],
//
//    'allowed_methods' => ['GET', 'POST', 'OPTIONS', '*'], // Allow all methods or specify like ['GET', 'POST']
//
//    'allowed_origins' => [
//        'https://web.molecularhydrogeninstitute.org', // Main Site
//        'https://webbackend.molecularhydrogeninstitute.org', // API Server
//    ],
//
//    'allowed_origins_patterns' => [], // Use for wildcard patterns if needed
//
//    'allowed_headers' => ['*'], // Allow all headers or specify like ['Content-Type', 'Authorization']
//
//    'exposed_headers' => [],
//
//    'max_age' => 0, // Specify how long the results of a preflight request can be cached
//
//    'supports_credentials' => true, // Important if you're using cookies or credentials
//];

