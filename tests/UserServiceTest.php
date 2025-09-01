<?php

use App\Services\UserService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserService::class)]
class UserServiceTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'user_test_');
        file_put_contents($this->tmpFile, json_encode([]));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testRegisterStoresAndReturnsUser()
    {
        $svc = new UserService($this->tmpFile);
        $user = $svc->register('alice', 'password123');
        $this->assertArrayHasKey('id', $user);
        $this->assertSame('alice', $user['username']);
        $this->assertArrayHasKey('password', $user);
        $this->assertNotEmpty($user['created_at']);
        $this->assertTrue(password_verify('password123', $user['password']));
    }

    public function testRegisterThrowsOnDuplicateUsername()
    {
        $svc = new UserService($this->tmpFile);
        $svc->register('bob', 'pw');
        $this->expectException(\RuntimeException::class);
        $svc->register('bob', 'pw2');
    }

    public function testLoginReturnsUserOnSuccess()
    {
        $svc = new UserService($this->tmpFile);
        $svc->register('carol', 'pw');
        $user = $svc->login('carol', 'pw');
        $this->assertSame('carol', $user['username']);
    }

    public function testLoginThrowsOnWrongPassword()
    {
        $svc = new UserService($this->tmpFile);
        $svc->register('dave', 'pw');
        $this->expectException(\RuntimeException::class);
        $svc->login('dave', 'wrongpw');
    }

    public function testLoginThrowsOnMissingUser()
    {
        $svc = new UserService($this->tmpFile);
        $this->expectException(\RuntimeException::class);
        $svc->login('eve', 'pw');
    }

    public function testFindUserReturnsUserOrNull()
    {
        $svc = new UserService($this->tmpFile);
        $svc->register('frank', 'pw');
        $user = $svc->findUser('frank');
        $this->assertNotNull($user);
        $this->assertSame('frank', $user['username']);
        $missing = $svc->findUser('ghost');
        $this->assertNull($missing);
    }
}
