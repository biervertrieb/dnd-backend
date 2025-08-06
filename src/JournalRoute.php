<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

$journalFile = __DIR__ . '/../data/journal.json';

if (!file_exists($journalFile)) {
    file_put_contents($journalFile, json_encode([]));
}

$app->get('/journal', function (Request $request, Response $response) use ($journalFile) {
    $data = json_decode(file_get_contents($journalFile), true);
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/journal', function (Request $request, Response $response) use ($journalFile) {
    $input = $request->getParsedBody();
    $title = $input['title'] ?? '';
    $body = $input['body'] ?? '';

    if (!$title || !$body) {
        $response->getBody()->write(json_encode(['error' => 'Missing title or body']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $Parsedown = new Parsedown();
    $html = $Parsedown->text($body);

    $entry = [
        'id' => uniqid(),
        'title' => $title,
        'body' => $body,
        'html' => $html,
        'created_at' => date('c'),
    ];

    $data = json_decode(file_get_contents($journalFile), true);
    $data[] = $entry;
    file_put_contents($journalFile, json_encode($data, JSON_PRETTY_PRINT));

    $response->getBody()->write(json_encode(['status' => 'ok', 'entry' => $entry]));
    return $response->withHeader('Content-Type', 'application/json');
});
