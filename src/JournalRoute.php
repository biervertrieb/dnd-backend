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
    try {
        $entry = $svc->addEntry($input['title'] ?? null, $input['body'] ?? null);
        $response->getBody()->write(json_encode(['status' => 'ok', 'entry' => $entry]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\RuntimeException $e) {
        $response->getBody()->write(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
});

$app->put('/journal/{id}', function (Request $request, Response $response, array $args) use ($svc) {
    $id = $args['id'];
    $input = $request->getParsedBody();
    try {
        $updated = $svc->updateEntry($id, $input['title'] ?? null, $input['body'] ?? null);
        $response->getBody()->write(json_encode(['status' => 'ok', 'entry' => $updated]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\RuntimeException $e) {
        $response->getBody()->write(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
});
