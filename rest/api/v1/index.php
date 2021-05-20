<?php
require __DIR__ . '/../vendor/autoload.php';

// Instantiate the app
$settings = require __DIR__ . '/src/settings.php';
$app = new \Slim\App($settings);

$container = $app->getContainer();


// custom_functions
require __DIR__ . '/src/custom_functions.php';

// Register dependencies
require __DIR__ . '/src/dependencies.php';

// Register middleware
require __DIR__ . '/src/middleware.php';

// Register routes
require __DIR__ . '/src/routes_get.php';
require __DIR__ . '/src/routes_post.php';
require __DIR__ . '/src/routes_put.php';
require __DIR__ . '/src/routes_delete.php';
require __DIR__ . '/src/routes_iserve.php';

require __DIR__ . '/src/functions.php';

// require_once 'ratchet/bin/chat-server.php';

$app->run();
