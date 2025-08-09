<?php

use App\JournalService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

$svc = new JournalService();

$app->get('/journal', function (Request $request, Response $response) use ($svc) {
    $data = $svc->getEntries();
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/journal', function (Request $request, Response $response) use ($svc) {
    $input = $request->getParsedBody();
    $title = $input['title'] ?? '';
    $body = $input['body'] ?? '';

    if (!$title || !$body) {
        $response->getBody()->write(json_encode(['error' => 'Missing title or body']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $entry = $svc->addEntry($title, $body);

    $response->getBody()->write(json_encode(['status' => 'ok', 'entry' => $entry]));
    return $response->withHeader('Content-Type', 'application/json');
});
