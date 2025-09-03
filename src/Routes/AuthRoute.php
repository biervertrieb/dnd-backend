<?php

use App\Middleware\JWTAuthMiddleware;
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
        $accessToken = JWT::encode(['id' => $user['id'], 'username' => $user['username']]);
        $refreshToken = JWT::encode(['id' => $user['id'], 'username' => $user['username'], 'exp' => time() + 604800]);  // 1 week
        $response->getBody()->write(json_encode(
            [
                'status' => 'ok',
                'accesToken' => $accessToken,
                'refreshToken' => $refreshToken,
                'user' => ['id' => $user['id'], 'username' => $user['username']]
            ]
        ));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\RuntimeException $e) {
        $response->getBody()->write(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }
});

$app->post('/refresh', function (Request $request, Response $response) {
    $refreshToken = $request->getAttribute('refreshToken');
    if (!$refreshToken) {
        $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'No refresh token provided']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }
    try {
        $user = JWT::decode($refreshToken);
        $accessToken = JWT::encode(['id' => $user['id'], 'username' => $user['username']]);
        $response->getBody()->write(json_encode(['status' => 'ok', 'accessToken' => $accessToken]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\RuntimeException $e) {
        $response->getBody()->write(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }
});

// TODO: Implement token blacklisting for logout if needed
$app->post('/logout', function (Request $request, Response $response) {
    // For stateless JWT, logout is handled client-side by discarding tokens
    $response->getBody()->write(json_encode(['status' => 'ok', 'message' => 'Logged out']));
    return $response->withHeader('Content-Type', 'application/json');
})->add(new JWTAuthMiddleware());

$app->get('/me', function (Request $request, Response $response) {
    $authHeader = $request->getHeaderLine('Authorization');
    if (!$authHeader || !preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
        $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'No token provided']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }
    $token = $matches[1];
    try {
        $user = JWT::decode($token);
        $response->getBody()->write(json_encode(['status' => 'ok', 'user' => ['id' => $user['id'], 'username' => $user['username']]]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\RuntimeException $e) {
        $response->getBody()->write(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }
})->add(new JWTAuthMiddleware());
