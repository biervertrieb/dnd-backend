<?php

use App\Middleware\JWTAuthMiddleware;
use App\Services\JournalService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

function registerJournalRoutes(App $app, JournalService $svc): void
{
    $app->group('/journal', function ($group) use ($svc) {
        $group->get('', function (Request $request, Response $response) use ($svc) {
            $data = $svc->getEntries();
            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');
        });

        $group->post('', function (Request $request, Response $response) use ($svc) {
            $input = $request->getParsedBody();
            $bodySize = strlen($request->getBody()->__toString());
            if (is_array($input)) {
                $bodySize = strlen(json_encode($input));
            }
            if ($bodySize > 1024 * 1024) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Payload too large']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            if (!is_array($input)) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Missing or invalid fields']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            if (!isset($input['title'])) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Missing Title']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            if (!isset($input['body'])) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Missing Body']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            if (!is_numeric($input['day']) || intval($input['day']) != $input['day']) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Day must be an integer']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $contentType = $request->getHeaderLine('Content-Type');
            if (strpos($contentType, 'application/json') === false) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Content-Type must be application/json']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            try {
                $entry = $svc->addEntry($input['title'] ?? null, $input['body'] ?? null, $input['day'] ?? null);
                $response->getBody()->write(json_encode(['status' => 'ok', 'entry' => $entry]));
                return $response->withHeader('Content-Type', 'application/json');
            } catch (\RuntimeException $e) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
        });

        $group->put('/{id}', function (Request $request, Response $response, array $args) use ($svc) {
            $id = $args['id'];
            $input = $request->getParsedBody();
            $bodySize = strlen($request->getBody()->__toString());
            if (is_array($input)) {
                $bodySize = strlen(json_encode($input));
            }
            if ($bodySize > 1024 * 1024) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Payload too large']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            if (!is_array($input)) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Missing or invalid fields']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            if (!isset($input['title'])) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Missing Title']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            if (!isset($input['body'])) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Missing Body']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            if (!is_numeric($input['day']) || intval($input['day']) != $input['day']) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Day must be an integer']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $contentType = $request->getHeaderLine('Content-Type');
            if (strpos($contentType, 'application/json') === false) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Content-Type must be application/json']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            try {
                $updated = $svc->updateEntry($id, $input['title'] ?? null, $input['body'] ?? null, $input['day'] ?? null);
                $response->getBody()->write(json_encode(['status' => 'ok', 'entry' => $updated]));
                return $response->withHeader('Content-Type', 'application/json');
            } catch (\RuntimeException $e) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
        });

        $group->delete('/{id}', function (Request $request, Response $response, array $args) use ($svc) {
            $id = $args['id'];
            $input = $request->getParsedBody();
            try {
                $updated = $svc->deleteEntry($id);
                $response->getBody()->write(json_encode(['status' => 'ok', 'entry' => $updated]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            } catch (\RuntimeException $e) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
        });
    })->add(new JWTAuthMiddleware());
}
