<?php

use Slim\App;

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Slim API is running.');
    return $response;
});

$app->addRoutingMiddleware();

$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setErrorHandler(
    Slim\Exception\HttpNotFoundException::class,
    function ($request, Throwable $exception, bool $displayErrorDetails) use ($app) {
        $response = $app->getResponseFactory()->createResponse();
        $response->getBody()->write(json_encode(['error' => 'Route not found']));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(404);
    }
);
