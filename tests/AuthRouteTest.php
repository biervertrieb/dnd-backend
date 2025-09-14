<?php

use App\Services\SessionService;
use App\Services\UserService;
use App\Util\JWT;
use Dotenv\Dotenv;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\App;

require_once __DIR__ . '/../src/Routes/AuthRoute.php';
require_once __DIR__ . '/HelperClasses.php';

#[CoversFunction('registerAuthRoutes')]
class AuthRouteTest extends TestCase
{
    private string $tmpUserFile;
    private string $tmpSessionFile;
    private UserService $userService;
    private TestableSessionService $sessionService;
    private App $app;

    public static function setUpBeforeClass(): void
    {
        $envPath = __DIR__ . '/../.env';
        if (!file_exists($envPath)) {
            throw new \RuntimeException(".env file not found at $envPath");
        }
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();
        if (empty($_ENV['JWT_SECRET'] ?? null)) {
            throw new \RuntimeException('JWT_SECRET not set in .env file');
        }
    }

    protected function setUp(): void
    {
        $this->tmpUserFile = tempnam(sys_get_temp_dir(), 'users_test_');
        $this->tmpSessionFile = tempnam(sys_get_temp_dir(), 'sessions_test_');
        file_put_contents($this->tmpUserFile, json_encode([]));
        file_put_contents($this->tmpSessionFile, json_encode([]));
        $this->userService = new TestableUserService($this->tmpUserFile);
        $this->sessionService = new TestableSessionService($this->tmpSessionFile);

        // Set up Slim app with routes
        $this->app = AppFactory::create(new ResponseFactory());
        registerAuthRoutes($this->app, $this->userService, $this->sessionService);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpUserFile)) {
            unlink($this->tmpUserFile);
        }
        if (file_exists($this->tmpSessionFile)) {
            unlink($this->tmpSessionFile);
        }
    }

    public function testRegisterEndpoint(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withParsedBody(['username' => 'newuser', 'password' => 'password123'])
            ->withHeader('Content-Type', 'application/json');

        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('id', $data['user']);
        $this->assertEquals('newuser', $data['user']['username']);
    }

    public function testRegisterEndpointDuplicateUsername(): void
    {
        $this->userService->register('existinguser', 'password123');

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'existinguser', 'password' => 'newpassword']);

        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testRegisterEndpointEmptyUsername(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => '', 'password' => 'password123']);

        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testRegisterEndpointEmptyPassword(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'newuser', 'password' => '']);

        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testRegisterEndpointWhitespaceUsername(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => '   ', 'password' => 'password123']);

        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testRegisterEndpointWhitespacePassword(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'newuser', 'password' => '   ']);

        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testRegisterEndpointShortUsername(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'ab', 'password' => 'password123']);

        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testRegisterEndpointLongUsername(): void
    {
        $longUsername = str_repeat('a', 51);
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => $longUsername, 'password' => 'password123']);

        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testRegisterEndpointShortPassword(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'newuser', 'password' => '123']);

        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testRegisterEndpointLongPassword(): void
    {
        $longPassword = str_repeat('a', 129);
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'newuser', 'password' => $longPassword]);

        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testRegisterEndpointInvalidCharactersInUsername(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'invalid user!', 'password' => 'password123']);

        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testRegisterEndpointInvalidCharactersInPassword(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'newuser', 'password' => "pass\nword"]);

        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testRegisterEndpointMissingUsername(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['password' => 'password123']);

        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testRegisterEndpointMissingPassword(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'newuser']);

        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testRegisterEndpointNonStringUsername(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => ['not', 'a', 'string', 123], 'password' => 'password123']);

        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testRegisterEndpointNonStringPassword(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'newuser', 'password' => [123456]]);

        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testRegisterEndpointTrimsUsernameAndPassword(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => '  trimmeduser  ', 'password' => '  password123  ']);

        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('id', $data['user']);
        $this->assertEquals('trimmeduser', $data['user']['username']);
    }

    public function testLoginEndpoint(): void
    {
        $this->userService->register('loginuser', 'password123');
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'loginuser', 'password' => 'password123']);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('accessToken', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertEquals('loginuser', $data['user']['username']);
        $this->assertNotEmpty($data['accessToken']);
        JWT::decode($data['accessToken']);  // will throw if invalid

        // Get all Set-Cookie headers
        $cookies = $response->getHeader('Set-Cookie');
        $this->assertNotEmpty($cookies, 'No Set-Cookie header found');

        // Check for the refresh token cookie
        $cookieFound = false;
        foreach ($cookies as $cookie) {
            if (str_starts_with($cookie, 'refreshToken=')) {
                $cookieFound = true;
                $this->assertStringContainsString('HttpOnly', $cookie);
                $this->assertStringContainsString('Path=/auth/refresh', $cookie);
                $this->assertStringContainsString('SameSite=Lax', $cookie);
                break;
            }
        }
        $this->assertTrue($cookieFound, 'refreshToken cookie not set');
    }

    public function testLoginEndpointInvalidPassword(): void
    {
        $this->userService->register('loginuser2', 'password123');
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'loginuser2', 'password' => 'wrongpassword']);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testLoginEndpointNonexistentUser(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'nonexistent', 'password' => 'password123']);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testLoginEndpointEmptyUsername(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => '', 'password' => 'password123']);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testLoginEndpointEmptyPassword(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'loginuser', 'password' => '']);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testLoginEndpointMissingUsername(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['password' => 'password123']);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testLoginEndpointMissingPassword(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'loginuser']);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testLoginEndpointNonStringUsername(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => [12345], 'password' => 'password123']);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testLoginEndpointNonStringPassword(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'loginuser', 'password' => [233456]]);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testLoginEndpointTrimsUsernameAndPassword(): void
    {
        $this->userService->register('trimmedlogin', 'password123');
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => '  trimmedlogin  ', 'password' => '  password123  ']);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('accessToken', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertEquals('trimmedlogin', $data['user']['username']);
        $this->assertNotEmpty($data['accessToken']);
        JWT::decode($data['accessToken']);  // will throw if invalid
    }

    public function testLoginEndpointCaseSensitiveUsername(): void
    {
        $this->userService->register('CaseSensitive', 'password123');
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'casesensitive', 'password' => 'password123']);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testLoginEndpointMultipleSessions(): void
    {
        $this->userService->register('multisession', 'password123');

        // First login
        $request1 = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'multisession', 'password' => 'password123']);
        $response1 = $this->app->handle($request1);
        $this->assertEquals(200, $response1->getStatusCode());
        $body1 = (string) $response1->getBody();
        $data1 = json_decode($body1, true);
        $this->assertArrayHasKey('accessToken', $data1);
        $this->assertArrayHasKey('user', $data1);
        $this->assertEquals('multisession', $data1['user']['username']);
        $this->assertNotEmpty($data1['accessToken']);
        JWT::decode($data1['accessToken']);  // will throw if invalid

        sleep(1);  // Ensure a time difference between tokens
        // Second login
        $request2 = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'multisession', 'password' => 'password123']);
        $response2 = $this->app->handle($request2);
        $this->assertEquals(200, $response2->getStatusCode());
        $body2 = (string) $response2->getBody();
        $data2 = json_decode($body2, true);
        $this->assertArrayHasKey('accessToken', $data2);
        $this->assertArrayHasKey('user', $data2);
        $this->assertEquals('multisession', $data2['user']['username']);
        $this->assertNotEmpty($data2['accessToken']);
        JWT::decode($data2['accessToken']);  // will throw if invalid

        // Ensure the two access tokens are different
        $this->assertNotEquals($data1['accessToken'], $data2['accessToken'], 'Access tokens for separate sessions should differ');

        // Check that two sessions exist in the session service
        $sessions = $this->sessionService->getSessions();

        $this->assertCount(2, $sessions, 'There should be two active sessions for the user');
    }

    public function testLoginEndpointCaseSensitivePassword(): void
    {
        $this->userService->register('casepassword', 'Password123');
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'casepassword', 'password' => 'password123']);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testRefreshEndpoint(): void
    {
        $session1 = $this->sessionService->createSession(1, 'refreshuser');
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/refresh')
            ->withCookieParams(['refreshToken' => $session1['refreshToken']]);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('accessToken', $data);
        $this->assertNotEmpty($data['accessToken']);
        $user = JWT::decode($data['accessToken']);  // will throw if invalid
        $this->assertEquals(1, $user['id']);
        $this->assertEquals('refreshuser', $user['username']);

        // Get all Set-Cookie headers
        $cookies = $response->getHeader('Set-Cookie');
        $this->assertNotEmpty($cookies, 'No Set-Cookie header found');
        // Check for the refresh token cookie
        $cookieFound = false;
        foreach ($cookies as $cookie) {
            if (str_starts_with($cookie, 'refreshToken=')) {
                $cookieFound = true;
                $this->assertStringContainsString('HttpOnly', $cookie);
                $this->assertStringContainsString('Path=/auth/refresh', $cookie);
                $this->assertStringContainsString('SameSite=Lax', $cookie);
                break;
            }
        }
        $this->assertTrue($cookieFound, 'refreshToken cookie not set');
    }

    public function testRefreshEndpointMissingCookie(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/refresh');
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testRefreshEndpointInvalidToken(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/refresh')
            ->withCookieParams(['refreshToken' => 'invalidtoken']);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testRefreshEndpointExpiredToken(): void
    {
        $session = $this->sessionService->createSession(1, 'expireduser');
        $this->sessionService->expireRT($session['refreshToken']);
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/refresh')
            ->withCookieParams(['refreshToken' => $session['refreshToken']]);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testRefreshEndpointExpiredSession(): void
    {
        $session = $this->sessionService->createSession(1, 'expiredsessionuser');
        $this->sessionService->expireSession($session['refreshToken']);
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/refresh')
            ->withCookieParams(['refreshToken' => $session['refreshToken']]);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
    }

    public function testRefreshEndpointMultipleSessions(): void
    {
        $session1 = $this->sessionService->createSession(1, 'multiuser');
        $session2 = $this->sessionService->createSession(1, 'multiuser');
        // Refresh the first session
        $request1 = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/refresh')
            ->withCookieParams(['refreshToken' => $session1['refreshToken']]);
        $response1 = $this->app->handle($request1);
        $this->assertEquals(200, $response1->getStatusCode());
        $body1 = (string) $response1->getBody();
        $data1 = json_decode($body1, true);
        $this->assertArrayHasKey('accessToken', $data1);
        $this->assertNotEmpty($data1['accessToken']);
        $user1 = JWT::decode($data1['accessToken']);  // will throw if invalid
        $this->assertEquals(1, $user1['id']);
        $this->assertEquals('multiuser', $user1['username']);
        sleep(1);  // Ensure a time difference between tokens
        // Refresh the second session
        $request2 = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/refresh')
            ->withCookieParams(['refreshToken' => $session2['refreshToken']]);
        $response2 = $this->app->handle($request2);
        $this->assertEquals(200, $response2->getStatusCode());
        $body2 = (string) $response2->getBody();
        $data2 = json_decode($body2, true);
        $this->assertArrayHasKey('accessToken', $data2);
        $this->assertNotEmpty($data2['accessToken']);
        $user2 = JWT::decode($data2['accessToken']);  // will throw if invalid
        $this->assertEquals(1, $user2['id']);
        $this->assertEquals('multiuser', $user2['username']);
        // Ensure the two access tokens are different
        $this->assertNotEquals($data1['accessToken'], $data2['accessToken'], 'Access tokens for separate sessions should differ');
        // Check that two sessions still exist in the session service
        // (Note: After refresh, the old refresh tokens are invalidated, but the sessions remain until they expire)
        $sessions = $this->sessionService->getSessions();
        $this->assertCount(2, $sessions, 'There should be two active sessions for the user');
    }

    public function testRefreshEndpointRefreshedTokenCanRefresh(): void
    {
        $session1 = $this->sessionService->createSession(1, 'chaineduser');
        // First refresh
        $request1 = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/refresh')
            ->withCookieParams(['refreshToken' => $session1['refreshToken']]);
        $response1 = $this->app->handle($request1);
        $this->assertEquals(200, $response1->getStatusCode());
        $body1 = (string) $response1->getBody();
        $data1 = json_decode($body1, true);
        $this->assertArrayHasKey('accessToken', $data1);
        $this->assertNotEmpty($data1['accessToken']);
        $user1 = JWT::decode($data1['accessToken']);  // will throw if invalid
        $this->assertEquals(1, $user1['id']);
        $this->assertEquals('chaineduser', $user1['username']);
        // Get the new refresh token from the Set-Cookie header
        $cookies = $response1->getHeader('Set-Cookie');
        $this->assertNotEmpty($cookies, 'No Set-Cookie header found');
        $newRefreshToken = null;
        foreach ($cookies as $cookie) {
            if (str_starts_with($cookie, 'refreshToken=')) {
                preg_match('/refreshToken=([^;]+)/', $cookie, $matches);
                if (isset($matches[1])) {
                    $newRefreshToken = $matches[1];
                }
                break;
            }
        }
        $this->assertNotNull($newRefreshToken, 'New refresh token not found in Set-Cookie header');

        // Second refresh using the new refresh token
        $request2 = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/refresh')
            ->withCookieParams(['refreshToken' => $newRefreshToken]);
        $response2 = $this->app->handle($request2);
        $this->assertEquals(200, $response2->getStatusCode());
        $body2 = (string) $response2->getBody();
        $data2 = json_decode($body2, true);
        $this->assertArrayHasKey('accessToken', $data2);
        $this->assertNotEmpty($data2['accessToken']);
        $user2 = JWT::decode($data2['accessToken']);  // will throw if invalid
        $this->assertEquals(1, $user2['id']);
        $this->assertEquals('chaineduser', $user2['username']);
    }

    public function testRefreshEndpointReusedToken(): void
    {
        $session = $this->sessionService->createSession(1, 'reuseuser');
        // First refresh
        $request1 = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/refresh')
            ->withCookieParams(['refreshToken' => $session['refreshToken']]);
        $response1 = $this->app->handle($request1);
        $this->assertEquals(200, $response1->getStatusCode());
        $body1 = (string) $response1->getBody();
        $data1 = json_decode($body1, true);
        $this->assertArrayHasKey('accessToken', $data1);
        $this->assertNotEmpty($data1['accessToken']);
        $user1 = JWT::decode($data1['accessToken']);  // will throw if invalid
        $this->assertEquals(1, $user1['id']);
        $this->assertEquals('reuseuser', $user1['username']);
        // Attempt to reuse the same refresh token
        $request2 = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/refresh')
            ->withCookieParams(['refreshToken' => $session['refreshToken']]);
        $response2 = $this->app->handle($request2);
        $this->assertEquals(400, $response2->getStatusCode());
        $body2 = (string) $response2->getBody();
        $data2 = json_decode($body2, true);
        $this->assertArrayHasKey('status', $data2);
        $this->assertEquals('error', $data2['status']);

        // Attempt to refresh with compromised token
        $cookieHeader = $response1->getHeader('Set-Cookie');
        $this->assertNotEmpty($cookieHeader, 'No Set-Cookie header found after refresh');
        $compromisedToken = null;
        foreach ($cookieHeader as $cookie) {
            if (str_starts_with($cookie, 'refreshToken=')) {
                preg_match('/refreshToken=([^;]+)/', $cookie, $matches);
                if (isset($matches[1])) {
                    $compromisedToken = $matches[1];
                }
                break;
            }
        }
        $this->assertNotNull($compromisedToken, 'Compromised token not found in Set-Cookie header');
        $request3 = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/refresh')
            ->withCookieParams(['refreshToken' => $compromisedToken]);
        $response3 = $this->app->handle($request3);
        $this->assertEquals(400, $response3->getStatusCode());
        $body3 = (string) $response3->getBody();
        $data3 = json_decode($body3, true);
        $this->assertArrayHasKey('status', $data3);
        $this->assertEquals('error', $data3['status']);
    }

    public function testLogoutEndpoint(): void
    {
        $session = $this->sessionService->createSession(1, 'logoutuser');
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/refresh/logout')
            ->withCookieParams(['refreshToken' => $session['refreshToken']]);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('message', $data);

        // Check that the session is removed
        $sessions = $this->sessionService->getSessions();
        $this->assertCount(0, $sessions, 'Session should be removed after logout');
    }

    // --- Missing Cases for Consistency ---

    public function testRegisterEndpointMalformedJson(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withHeader('Content-Type', 'application/json');
        $body = '{"username": "user", "password": "missing end quote}';
        $request = $request->withBody((new \Slim\Psr7\Stream(fopen('php://temp', 'r+'))));
        $request->getBody()->write($body);
        $request->getBody()->rewind();
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testRegisterEndpointWrongContentType(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withHeader('Content-Type', 'text/plain')
            ->withParsedBody(['username' => 'user', 'password' => 'password123']);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testRegisterEndpointExtraFields(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'user', 'password' => 'password123', 'extra' => 'field']);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('user', $data);
    }

    public function testRegisterEndpointDuplicateKeys(): void
    {
        $rawJson = '{"username": "user", "username": "duplicate", "password": "password123"}';
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withHeader('Content-Type', 'application/json');
        $stream = new \Slim\Psr7\Stream(fopen('php://temp', 'r+'));
        $stream->write($rawJson);
        $stream->rewind();
        $request = $request->withBody($stream);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testRegisterEndpointBoundaryValues(): void
    {
        $username = str_repeat('U', 50);
        $password = str_repeat('P', 128);
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => $username, 'password' => $password]);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals($username, $data['user']['username']);
    }

    public function testRegisterEndpointUnicodeCharacters(): void
    {
        $username = 'Tést🚀';
        $password = 'Pässwörd💡';
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => $username, 'password' => $password]);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testRegisterEndpointPayloadTooLarge(): void
    {
        $largepayload = str_repeat('U', 2000000);
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'large', 'password' => 'payload incoming', 'payload' => $largepayload]);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testLoginEndpointMalformedJson(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/login')
            ->withHeader('Content-Type', 'application/json');
        $body = '{"username": "user", "password": "missing end brace"';
        $request = $request->withBody((new \Slim\Psr7\Stream(fopen('php://temp', 'r+'))));
        $request->getBody()->write($body);
        $request->getBody()->rewind();
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testLoginEndpointWrongContentType(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/login')
            ->withHeader('Content-Type', 'text/plain')
            ->withParsedBody(['username' => 'user', 'password' => 'password123']);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testLoginEndpointExtraFields(): void
    {
        $this->userService->register('user', 'password123');
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'user', 'password' => 'password123', 'extra' => 'field']);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('accessToken', $data);
    }

    public function testLoginEndpointDuplicateKeys(): void
    {
        $rawJson = '{"username": "user", "username": "duplicate", "password": "password123"}';
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/login')
            ->withHeader('Content-Type', 'application/json');
        $stream = new \Slim\Psr7\Stream(fopen('php://temp', 'r+'));
        $stream->write($rawJson);
        $stream->rewind();
        $request = $request->withBody($stream);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testLoginEndpointBoundaryValues(): void
    {
        $username = str_repeat('U', 50);
        $password = str_repeat('P', 128);
        $this->userService->register($username, $password);
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => $username, 'password' => $password]);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals($username, $data['user']['username']);
    }

    public function testLoginEndpointPayloadTooLarge(): void
    {
        $largepayload = str_repeat('U', 1024 * 1024);
        $this->userService->register('largeuser', 'payloadincoming');
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['username' => 'largeuser', 'password' => 'payloadincoming', 'payload' => $largepayload]);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testRefreshEndpointExtraFields(): void
    {
        $session = $this->sessionService->createSession(1, 'user');
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/refresh')
            ->withCookieParams(['refreshToken' => $session['refreshToken'], 'extra' => 'field']);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('accessToken', $data);
    }

    public function testLogoutEndpointExtraFields(): void
    {
        $session = $this->sessionService->createSession(1, 'user');
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/refresh/logout')
            ->withCookieParams(['refreshToken' => $session['refreshToken'], 'extra' => 'field']);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('message', $data);
    }

    public function testUnsupportedHttpMethods(): void
    {
        // PATCH: Slim throws HttpMethodNotAllowedException
        $this->expectException(\Slim\Exception\HttpMethodNotAllowedException::class);
        $request = (new ServerRequestFactory())
            ->createServerRequest('PATCH', '/auth/register');
        $this->app->handle($request);

        try {
            $request = (new ServerRequestFactory())
                ->createServerRequest('OPTIONS', '/auth/register');
            $response = $this->app->handle($request);
            $this->assertTrue(in_array($response->getStatusCode(), [200, 404, 405]));
        } catch (\Slim\Exception\HttpMethodNotAllowedException $e) {
            $this->assertTrue(true);
        }

        try {
            $request = (new ServerRequestFactory())
                ->createServerRequest('HEAD', '/auth/register');
            $response = $this->app->handle($request);
            $this->assertTrue(in_array($response->getStatusCode(), [200, 404, 405]));
        } catch (\Slim\Exception\HttpMethodNotAllowedException $e) {
            $this->assertTrue(true);
        }
    }
}
