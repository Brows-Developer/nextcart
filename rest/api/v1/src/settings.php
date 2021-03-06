<?php
return [
    'settings' => [
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . 'templates/',
        ],

        // Monolog settings
        'logger' => [
            'name' => 'rest_api_v1',
            'path' => __DIR__ . '/../../logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],

        // DB Settings
        'db' => [
            'host' => '127.0.0.1',
            'name' => 'nextcart',
            'user' => 'gerardo',
            'password' => 'PassW0rd!!'
        ],

        'key' => 'anakeydonotcommitsettingsphp'

    ],
];
