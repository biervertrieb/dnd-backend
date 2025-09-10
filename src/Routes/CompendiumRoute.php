<?php

use App\Middleware\JWTAuthMiddleware;
use App\Services\CompendiumService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

function registerCompendiumRoutes(App $app, CompendiumService $comp_svc): void
{
    $app->group('/compendium', function ($group) use ($comp_svc) {
        $group->get('', function (Request $request, Response $response) use ($comp_svc) {
            $data = $comp_svc->getEntries();
            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');
        });

        $group->get('/{key}', function (Request $request, Response $response, array $args) use ($comp_svc) {
            $key = $args['key'];
            try {
                $loaded = $comp_svc->getBySlug($key) ?? $comp_svc->getByID($key);
                $response->getBody()->write(json_encode(['status' => 'ok', 'entry' => $loaded]));
                return $response->withHeader('Content-Type', 'application/json');
            } catch (\RuntimeException $e) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
        });

        $group->post('', function (Request $request, Response $response) use ($comp_svc) {
            $input = $request->getParsedBody();
            try {
                $entry = $comp_svc->addEntry($input['title'] ?? null, $input['body'] ?? null, $input['tags'] ?? []);
                $response->getBody()->write(json_encode(['status' => 'ok', 'entry' => $entry]));
                return $response->withHeader('Content-Type', 'application/json');
            } catch (\RuntimeException $e) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
        });

        $group->put('/{id}', function (Request $request, Response $response, array $args) use ($comp_svc) {
            $id = $args['id'];
            $input = $request->getParsedBody();
            try {
                $updated = $comp_svc->updateEntry($id, $input['title'] ?? null, $input['body'] ?? null, $input['tags'] ?? []);
                $response->getBody()->write(json_encode(['status' => 'ok', 'entry' => $updated]));
                return $response->withHeader('Content-Type', 'application/json');
            } catch (\RuntimeException $e) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
        });

        $group->delete('/{id}', function (Request $request, Response $response, array $args) use ($comp_svc) {
            $id = $args['id'];
            $input = $request->getParsedBody();
            try {
                $updated = $comp_svc->deleteEntry($id);
                $response->getBody()->write(json_encode(['status' => 'ok', 'entry' => $updated]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            } catch (\RuntimeException $e) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
        });
    })->add(new JWTAuthMiddleware());
}
