<?php

use App\Middleware\JWTAuthMiddleware;
use App\Services\SessionService;
use App\Services\UserService;
use App\Util\JWT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

function addRefreshCookie(Response $response, string $refreshToken): Response
{
    return $response->withHeader('Set-Cookie',
        'refreshToken=' . $refreshToken . ';'
            . ' HttpOnly;'
            . ' SameSite=Lax;'
            . ' Path=/auth/refresh;'
            // . ' Secure;' // TODO: Uncomment this line if using HTTPS
            . ' Max-Age=604800');
}

function removeRefreshCookie(Response $response): Response
{
    return $response->withHeader('Set-Cookie',
        'refreshToken=;'
            . ' HttpOnly;'
            . ' SameSite=Lax;'
            . ' Path=/auth/refresh;'
            // . ' Secure;' // TODO: Uncomment this line if using HTTPS
            . ' Max-Age=0');
}

function registerAuthRoutes(App $app, UserService $userSvc, SessionService $sessSvc): void
{
    $app->group('/auth', function ($group) use ($userSvc, $sessSvc) {
        /**
         * register authentication route
         * provides a way to register a new account from username and password
         * request: POST /register
         * body: {username: string, password: string}
         * response body: {status: 'ok', user: {id: int, username: string}} or {status: 'error', message: string}
         */
        $group->post('/register', function (Request $request, Response $response) use ($userSvc) {
            $contentType = $request->getHeaderLine('Content-Type');
            if (strpos($contentType, 'application/json') === false) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Content-Type must be application/json']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
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
            if (!isset($input['username'])) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Missing Username']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            if (!is_string($input['username']) || trim($input['username']) === '') {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Username must be a non-empty string']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            if (!isset($input['password'])) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Missing Password']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            if (!is_string($input['password']) || trim($input['password']) === '') {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Password must be a non-empty string']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            try {
                $user = $userSvc->register($input['username'] ?? '', $input['password'] ?? '');
                $response->getBody()->write(json_encode(['status' => 'ok', 'user' => ['id' => $user['id'], 'username' => $user['username']]]));
                return $response->withHeader('Content-Type', 'application/json');
            } catch (\RuntimeException $e) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            } catch (\Exception $e) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Internal server error']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
        });

        /**
         * login authentication route
         * provides a way to login with username and password
         * request: POST /login
         * body: {username: string, password: string}
         * response body: {status: 'ok', accessToken: string, user: {id: int, username: string}} or {status: 'error', message: string}
         * sets a HttpOnly cookie with refresh token
         */
        $group->post('/login', function (Request $request, Response $response) use ($userSvc, $sessSvc) {
            $contentType = $request->getHeaderLine('Content-Type');
            if (strpos($contentType, 'application/json') === false) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Content-Type must be application/json']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
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
            if (!isset($input['username'])) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Missing Username']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            if (!is_string($input['username']) || trim($input['username']) === '') {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Username must be a non-empty string']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            if (!isset($input['password'])) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Missing Password']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            if (!is_string($input['password']) || trim($input['password']) === '') {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Password must be a non-empty string']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            try {
                $user = $userSvc->verifyLogin($input['username'] ?? '', $input['password'] ?? '');
                $session = $sessSvc->createSession((int) $user['id'], $user['username']);
                $response->getBody()->write(json_encode(
                    [
                        'status' => 'ok',
                        'accessToken' => $session['accessToken'],
                        'expiresAt' => $session['exp'],
                        'user' => ['id' => $user['id'], 'username' => $user['username']]
                    ]
                ));
                $response = addRefreshCookie($response, $session['refreshToken']);
                return $response
                    ->withHeader('Content-Type', 'application/json');
            } catch (\RuntimeException $e) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            } catch (\Exception $e) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Internal server error']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
        });

        /**
         * refresh authentication route
         * provides a way to refresh access token with refresh token
         * request: POST /refresh
         * must provide refresh token as cookie
         * response: {status: 'ok', accessToken: string, user: {id: int, username: string}} or {status: 'error', message: string}
         */
        $group->post('/refresh', function (Request $request, Response $response) use ($sessSvc) {
            $cookies = $request->getCookieParams();
            $refreshToken = $cookies['refreshToken'] ?? null;
            if (!$refreshToken) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'No refresh token provided']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            try {
                $session = $sessSvc->refreshSession($refreshToken);
                $response->getBody()->write(json_encode(
                    [
                        'status' => 'ok',
                        'accessToken' => $session['accessToken'],
                        'expiresAt' => $session['exp'],
                        'user' => ['id' => $session['user_id'], 'username' => $session['username']]
                    ]
                ));
                // Rotate refresh token
                $response = addRefreshCookie($response, $session['refreshToken']);
                return $response->withHeader('Content-Type', 'application/json');
            } catch (\RuntimeException $e) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            } catch (\Exception $e) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Internal server error']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
        });

        /**
         * logout authentication route
         * provides a way to logout and invalidate tokens
         * request: POST /logout
         * response body: {status: 'ok'|'error', message: string}
         * sets a HttpOnly cookie with empty refresh token
         */
        $group->post('/refresh/logout', function (Request $request, Response $response) use ($sessSvc) {
            $cookies = $request->getCookieParams();
            $refreshToken = $cookies['refreshToken'] ?? null;
            if ($refreshToken) {
                // If there's a refresh token, try to invalidate the session
                try {
                    $sessSvc->invalidateSession($refreshToken);
                } catch (\Exception $e) {
                    // Ignore errors here, as we want to log out anyway
                }
            }
            $response->getBody()->write(json_encode(['status' => 'ok', 'message' => 'Logged out']));
            $response = removeRefreshCookie($response);
            return $response->withHeader('Content-Type', 'application/json');
        });

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
            } catch (\Exception $e) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Internal server error']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
        })->add(new JWTAuthMiddleware());
    });
}
