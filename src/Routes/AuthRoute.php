<?php

use App\Services\UserService;
use App\Util\JWT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

$userSvc = new UserService();

$app->post('/register', function (Request $request, Response $response) use ($userSvc) {
    $input = $request->getParsedBody();
    try {
        $user = $userSvc->register($input['username'] ?? '', $input['password'] ?? '');
        $response->getBody()->write(json_encode(['status' => 'ok', 'user' => ['id' => $user['id'], 'username' => $user['username']]]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\RuntimeException $e) {
        $response->getBody()->write(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
});

$app->post('/login', function (Request $request, Response $response) use ($userSvc) {
    $input = $request->getParsedBody();
    try {
        $user = $userSvc->login($input['username'] ?? '', $input['password'] ?? '');
        $token = JWT::encode(['id' => $user['id'], 'username' => $user['username']]);
        $response->getBody()->write(json_encode(['status' => 'ok', 'token' => $token]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\RuntimeException $e) {
        $response->getBody()->write(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }
});
