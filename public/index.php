<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

require __DIR__ . '/../src/cors.php';
require __DIR__ . '/../src/JournalRoute.php';
require __DIR__ . '/../src/FallbackRoute.php';
require __DIR__ . '/../src/options.php';

$app->run();
