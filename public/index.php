<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Services\JournalService;
use Dotenv\Dotenv;

Dotenv::createImmutable(__DIR__ . '/../')->load();

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

registerJournalRoutes($app, JournalService::getInstance());

$app->run();
