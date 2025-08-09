<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});
