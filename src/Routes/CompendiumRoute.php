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
            $bodySize = strlen($request->getBody()->__toString());
            if ($bodySize > 1024 * 1024) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Payload too large']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(413);
            }
            $contentType = $request->getHeaderLine('Content-Type');
            if (strpos($contentType, 'application/json') === false) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Content-Type must be application/json']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            // Accept duplicate keys: as long as parsed body is array and has required keys, allow
            if (!is_array($input) || !array_key_exists('title', $input) || !array_key_exists('body', $input) || !array_key_exists('tags', $input) || !is_array($input['tags'])) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Missing or invalid fields']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $title = is_string($input['title']) ? trim($input['title']) : '';
            $body = is_string($input['body']) ? trim($input['body']) : '';
            $tags = array_map(function ($tag) {
                return is_string($tag) ? trim($tag) : $tag;
            }, $input['tags']);
            if ($title === '' || $body === '') {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Title and body cannot be empty or whitespace only']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            foreach ($tags as $tag) {
                if (!is_string($tag) || $tag === '' || mb_strlen($tag) > 50) {
                    $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Tag must be non-empty and ≤ 50 chars']));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }
            }
            try {
                $entry = $comp_svc->addEntry($title, $body, $tags);
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
            $bodySize = strlen($request->getBody()->__toString());
            if ($bodySize > 1024 * 1024) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Payload too large']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(413);
            }
            $contentType = $request->getHeaderLine('Content-Type');
            if (strpos($contentType, 'application/json') === false) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Content-Type must be application/json']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            if (!is_array($input) || !isset($input['title']) || !isset($input['body']) || !isset($input['tags']) || !is_array($input['tags'])) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Missing or invalid fields']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $title = is_string($input['title']) ? trim($input['title']) : '';
            $body = is_string($input['body']) ? trim($input['body']) : '';
            $tags = array_map(function ($tag) {
                return is_string($tag) ? trim($tag) : $tag;
            }, $input['tags']);
            if ($title === '' || $body === '') {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Title and body cannot be empty or whitespace only']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            foreach ($tags as $tag) {
                if (!is_string($tag) || $tag === '' || mb_strlen($tag) > 50) {
                    $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Tag must be non-empty and ≤ 50 chars']));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }
            }
            try {
                $updated = $comp_svc->updateEntry($id, $title, $body, $tags);
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
