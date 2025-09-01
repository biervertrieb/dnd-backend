<?php
namespace App\Middleware;

use App\Util\JWT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

class JWTAuthMiddleware
{
    public function __invoke(Request $request, Handler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
            return $this->unauthorized();
        }
        $token = $matches[1];
        try {
            $user = JWT::decode($token);
            // Check for required claims
            if (!isset($user['id'], $user['username'])) {
                return $this->unauthorized();
            }
            // Check for expiration
            if (!isset($user['exp']) || $user['exp'] < time()) {
                return $this->unauthorized();
            }
            $request = $request->withAttribute('user', $user);
            return $handler->handle($request);
        } catch (\RuntimeException $e) {
            return $this->unauthorized();
        }
    }

    private function unauthorized(): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }
}
