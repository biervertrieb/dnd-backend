<?php

use App\CompendiumService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

$comp_svc = new CompendiumService();

$app->get('/compendium', function (Request $request, Response $response) use ($comp_svc) {
    $data = $comp_svc->getEntries();
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/compendium', function (Request $request, Response $response) use ($comp_svc) {
    $input = $request->getParsedBody();
    try {
        $entry = $comp_svc->addEntry($input['title'] ?? null, $input['body'] ?? null, $input['tags'] ?? null);
        $response->getBody()->write(json_encode(['status' => 'ok', 'entry' => $entry]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\RuntimeException $e) {
        $response->getBody()->write(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
});

$app->put('/compendium/{id}', function (Request $request, Response $response, array $args) use ($comp_svc) {
    $id = $args['id'];
    $input = $request->getParsedBody();
    try {
        $updated = $comp_svc->updateEntry($id, $input['title'] ?? null, $input['body'] ?? null, $input['tags'] ?? null);
        $response->getBody()->write(json_encode(['status' => 'ok', 'entry' => $updated]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\RuntimeException $e) {
        $response->getBody()->write(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
});

$app->delete('/compendium/{id}', function (Request $request, Response $response, array $args) use ($comp_svc) {
    $id = $args['id'];
    $input = $request->getParsedBody();
    try {
        $updated = $comp_svc->deleteEntry($id);
        $response->getBody()->write(json_encode(['status' => 'ok', 'entry' => $updated]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(204);
    } catch (\RuntimeException $e) {
        $response->getBody()->write(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
});
