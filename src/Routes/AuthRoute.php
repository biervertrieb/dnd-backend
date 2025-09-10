<?php

use App\Middleware\JWTAuthMiddleware;
use App\Services\UserService;
use App\Util\JWT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

function registerAuthRoutes(App $app, UserService $userSvc): void
{
    $app->group('/auth', function ($group) use ($userSvc) {
        /**
         * register authentication route
         * provides a way to register a new account from username and password
         * POST /register
         * body: {username: string, password: string}
         * response: {status: 'ok', user: {id: int, username: string}} or {status: 'error', message: string}
         */
        $group->post('/register', function (Request $request, Response $response) use ($userSvc) {
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

        /**
         * login authentication route
         * provides a way to login with username and password
         * POST /login
         * body: {username: string, password: string}
         * response: {status: 'ok', accessToken: string, refreshToken: string, user: {id: int, username: string}} or {status: 'error', message: string}
         */
        $group->post('/login', function (Request $request, Response $response) use ($userSvc) {
            $input = $request->getParsedBody();
            try {
                $user = $userSvc->login($input['username'] ?? '', $input['password'] ?? '');
                $accessToken = JWT::encode(['id' => $user['id'], 'username' => $user['username']]);
                $refreshToken = JWT::encode(['id' => $user['id'], 'username' => $user['username'], 'exp' => time() + 604800]);  // 1 week
                $response->getBody()->write(json_encode(
                    [
                        'status' => 'ok',
                        'accessToken' => $accessToken,
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

        /**
         * refresh authentication route
         * provides a way to refresh access token with refresh token
         * POST /refresh
         * body: {refreshToken: string}
         * response: {status: 'ok', accessToken: string, refreshToken: string, user: {id: int, username: string}} or {status: 'error', message: string}
         */
        $group->post('/refresh', function (Request $request, Response $response) {
            $input = $request->getParsedBody();
            $refreshToken = $input['refreshToken'] ?? null;
            if (!$refreshToken) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'No refresh token provided']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }
            try {
                if (JWT::isTokenInvalidated($refreshToken)) {
                    $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Refresh token has been invalidated']));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
                }
                $user = JWT::decode($refreshToken);
                if ($user['exp'] ?? 0 < time()) {
                    $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Token expired']));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
                }
                $accessToken = JWT::encode(['id' => $user['id'], 'username' => $user['username']]);
                $response->getBody()->write(json_encode(
                    [
                        'status' => 'ok',
                        'accessToken' => $accessToken,
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

        /**
         * logout authentication route
         * provides a way to logout and invalidate tokens
         * POST /logout
         * header: {Authorization Bearer <accessToken>}
         * body: {refreshToken: string}
         * response: {status: 'ok'|'error', message: string}
         */
        $group->post('/logout', function (Request $request, Response $response) {
            $authHeader = $request->getHeaderLine('Authorization');
            if (!$authHeader || !preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'No access token provided']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }
            $accessToken = $matches[1];
            try {
                JWT::decode($accessToken);
            } catch (\RuntimeException $e) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Invalid access token: ' . $e->getMessage()]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }
            $input = $request->getParsedBody();
            $refreshToken = $input['refreshToken'] ?? null;
            if (!$refreshToken) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'No refresh token provided']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }
            try {
                JWT::decode($refreshToken);
            } catch (\RuntimeException $e) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Invalid refresh token: ' . $e->getMessage()]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }
            try {
                JWT::invalidateToken($accessToken);
                JWT::invalidateToken($refreshToken);
            } catch (Exception $e) {
                // Log the error but continue
                // TODO: do something with the error
            }
            $response->getBody()->write(json_encode(['status' => 'ok', 'message' => 'Logged out']));
            return $response->withHeader('Content-Type', 'application/json');
        })->add(new JWTAuthMiddleware());

        /**
         * get current user route
         * provides a way to get current logged in user info
         * GET /me
         * header: {Authorization Bearer <accessToken>}
         * response: {status: 'ok', user: {id: int, username: string}} or {status: 'error', message: string}
         */
        $group->get('/me', function (Request $request, Response $response) {
            $authHeader = $request->getHeaderLine('Authorization');
            if (!$authHeader || !preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'No token provided']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }
            $token = $matches[1];
            try {
                $user = JWT::decode($token);
                // Token was already validated in middleware, so just return user info
                $response->getBody()->write(json_encode(
                    [
                        'status' => 'ok',
                        'user' => ['id' => $user['id'], 'username' => $user['username']]
                    ]
                ));
                return $response->withHeader('Content-Type', 'application/json');
            } catch (\RuntimeException $e) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }
        })->add(new JWTAuthMiddleware());
    });
}
