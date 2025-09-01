<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

require __DIR__ . '/../src/cors.php';
require __DIR__ . '/../src/options.php';

// Automatically require all Route.php files from src/routes/
$routeFiles = glob(__DIR__ . '/../src/Routes/*Route.php');
foreach ($routeFiles as $file) {
    require $file;
}

$app->run();
