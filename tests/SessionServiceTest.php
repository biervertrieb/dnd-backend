<?php

use App\Services\SessionService;
use App\Util\JWT;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/HelperClasses.php';

#[CoversClass(SessionService::class)]
class SessionServiceTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'session_test_');
        file_put_contents($this->tmpFile, json_encode([]));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testCreateSessionReturnsData()
    {
        $this->assertTrue(class_exists(\App\Services\SessionService::class));
        $service = new TestableSessionService($this->tmpFile);
        $session = $service->createSession(1, 'testuser');
        $this->assertArrayHasKey('refreshToken', $session);
        $this->assertArrayHasKey('accessToken', $session);
        $user = JWT::decode($session['accessToken']);
        $this->assertSame(1, $user['id']);
        $this->assertSame('testuser', $user['username']);
    }

    public function testCreateSessionThrowsOnEmptyName()
    {
        $service = new TestableSessionService($this->tmpFile);
        $this->expectException(\RuntimeException::class);
        $service->createSession(1, '');
    }

    public function testCreateSessionThrowsOnInvalidUserId()
    {
        $service = new TestableSessionService($this->tmpFile);
        $this->expectException(\RuntimeException::class);
        $service->createSession(0, 'testuser');
    }

    public function testCreateSessionThrowsOnNonIntegerUserId()
    {
        $service = new TestableSessionService($this->tmpFile);
        $this->expectException(\TypeError::class);
        $service->createSession('one', 'testuser');
    }

    public function testCreateSessionThrowsOnNullUserId()
    {
        $service = new TestableSessionService($this->tmpFile);
        $this->expectException(\TypeError::class);
        $service->createSession(null, 'testuser');
    }

    public function testCreateSessionThrowsOnNullUsername()
    {
        $service = new TestableSessionService($this->tmpFile);
        $this->expectException(\TypeError::class);
        $service->createSession(1, null);
    }

    public function testCreateSessionThrowsOnNonStringUsername()
    {
        $service = new TestableSessionService($this->tmpFile);
        $this->expectException(\TypeError::class);
        $service->createSession(1, ['testuser', 'another']);
    }

    public function testRefreshSessionReturnsNewTokens()
    {  // also tests that add session stores correctly
        $service = new TestableSessionService($this->tmpFile);
        $session1 = $service->createSession(1, 'testuser');
        sleep(1);  // Ensure time difference
        $session2 = $service->refreshSession($session1['refreshToken']);
        $this->assertArrayHasKey('refreshToken', $session2);
        $this->assertArrayHasKey('accessToken', $session2);
        $this->assertNotSame($session1['refreshToken'], $session2['refreshToken']);
        $this->assertNotSame($session1['accessToken'], $session2['accessToken']);
        $user = JWT::decode($session2['accessToken']);
        $this->assertSame(1, $user['id']);
        $this->assertSame('testuser', $user['username']);
    }

    public function testRefreshSessionDetectsReusedToken()
    {
        $service = new TestableSessionService($this->tmpFile);
        $session1 = $service->createSession(1, 'testuser');
        $session2 = $service->refreshSession($session1['refreshToken']);
        // Attempt to reuse the first token
        $this->expectException(\RuntimeException::class);
        $service->refreshSession($session1['refreshToken']);
    }

    public function testRefreshSessionInvalidatesOnReusedToken()
    {
        $service = new TestableSessionService($this->tmpFile);
        $session1 = $service->createSession(1, 'testuser');
        $session2 = $service->refreshSession($session1['refreshToken']);
        // Attempt to reuse the first token
        try {
            $service->refreshSession($session1['refreshToken']);
        } catch (\RuntimeException $e) {
            // Expected
        }
        // Now the second token should also be invalid
        $this->expectException(\RuntimeException::class);
        $service->refreshSession($session2['refreshToken']);
    }

    public function testRefreshSessionThrowsOnInvalidToken()
    {
        $service = new TestableSessionService($this->tmpFile);
        $this->expectException(\RuntimeException::class);
        $service->refreshSession('invalidtoken');
    }

    public function testRefreshSessionThrowsOnExpiredToken()
    {
        $service = new TestableSessionService($this->tmpFile);
        $session = $service->createSession(1, 'testuser');
        // Manually expire the session
        $service->expireRT($session['refreshToken']);
        $this->expectException(\RuntimeException::class);
        $service->refreshSession($session['refreshToken']);
    }

    public function testRefreshSessionThrowsOnExpiredSession()
    {
        $service = new TestableSessionService($this->tmpFile);
        $session = $service->createSession(1, 'testuser');
        // Manually expire the session
        $service->expireSession($session['refreshToken']);
        $this->expectException(\RuntimeException::class);
        $service->refreshSession($session['refreshToken']);
    }

    public function testRefreshSessionThrowsOnNonStringToken()
    {
        $service = new TestableSessionService($this->tmpFile);
        $this->expectException(\TypeError::class);
        $service->refreshSession(['not', 'a', 'string']);
    }

    public function testInvalidateSessionRemovesIt()
    {
        $service = new TestableSessionService($this->tmpFile);
        $session = $service->createSession(1, 'testuser');
        $service->invalidateSession($session['refreshToken']);
        $this->expectException(\RuntimeException::class);
        $service->refreshSession($session['refreshToken']);
    }

    public function testInvalidateSessionThrowsOnNonStringToken()
    {
        $service = new TestableSessionService($this->tmpFile);
        $this->expectException(\TypeError::class);
        $service->invalidateSession(['not', 'a', 'string']);
    }
}
