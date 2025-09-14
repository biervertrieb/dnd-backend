<?php

use App\Services\UserService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/HelperClasses.php';

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
        $svc = new TestableUserService($this->tmpFile);
        $user = $svc->register('alice', 'password123');
        $this->assertArrayHasKey('id', $user);
        $this->assertSame('alice', $user['username']);
        $this->assertArrayHasKey('password', $user);
        $this->assertNotEmpty($user['created_at']);
        $this->assertTrue(password_verify('password123', $user['password']));
    }

    public function testRegisterThrowsOnDuplicateUsername()
    {
        $svc = new TestableUserService($this->tmpFile);
        $svc->register('bob', 'pw1234');
        $this->expectException(\RuntimeException::class);
        $svc->register('bob', 'pw2345');
    }

    public function testRegisterThrowsOnEmptyUsername()
    {
        $svc = new TestableUserService($this->tmpFile);
        $this->expectException(\RuntimeException::class);
        $svc->register('', 'pw1234');
    }

    public function testRegisterThrowsOnEmptyPassword()
    {
        $svc = new TestableUserService($this->tmpFile);
        $this->expectException(\RuntimeException::class);
        $svc->register('charlie', '');
    }

    public function testRegisterThrowsOnWhitespaceUsername()
    {
        $svc = new TestableUserService($this->tmpFile);
        $this->expectException(\RuntimeException::class);
        $svc->register('   ', 'pw1234');
    }

    public function testRegisterThrowsOnWhitespacePassword()
    {
        $svc = new TestableUserService($this->tmpFile);
        $this->expectException(\RuntimeException::class);
        $svc->register('dave', '       ');
    }

    public function testRegisterThrowsOnShortPassword()
    {
        $svc = new TestableUserService($this->tmpFile);
        $this->expectException(\RuntimeException::class);
        $svc->register('eve', '123');
    }

    public function testRegisterThrowsOnLongUsername()
    {
        $svc = new TestableUserService($this->tmpFile);
        $longUsername = str_repeat('a', 51);
        $this->expectException(\RuntimeException::class);
        $svc->register($longUsername, 'validPassword');
    }

    public function testRegisterThrowsOnLongPassword()
    {
        $svc = new TestableUserService($this->tmpFile);
        $longPassword = str_repeat('a', 129);
        $this->expectException(\RuntimeException::class);
        $svc->register('validUser', $longPassword);
    }

    public function testRegisterTrimsUsername()
    {
        $svc = new TestableUserService($this->tmpFile);
        $user = $svc->register('  gregor  ', 'pw1234');
        $this->assertSame('gregor', $user['username']);
    }

    public function testRegisterTrimsPassword()
    {
        $svc = new TestableUserService($this->tmpFile);
        $user = $svc->register('grace', '  pw1234  ');
        $this->assertTrue(password_verify('pw1234', $user['password']));
    }

    public function testRegisterIsCaseSensitive()
    {
        $svc = new TestableUserService($this->tmpFile);
        $svc->register('Heidi', 'pw1234');
        $user = $svc->register('heidi', 'pw2345');
        $this->assertSame('heidi', $user['username']);
    }

    public function testRegisterThrowsOnNonStringUsername()
    {
        $svc = new TestableUserService($this->tmpFile);
        $this->expectException(\TypeError::class);
        $svc->register(null, 'pw1234');
    }

    public function testRegisterThrowsOnNonStringPassword()
    {
        $svc = new TestableUserService($this->tmpFile);
        $this->expectException(\TypeError::class);
        $svc->register('ivan', null);
    }

    public function testRegisterThrowsOnInvalidUsernameCharacters()
    {
        $svc = new TestableUserService($this->tmpFile);
        $this->expectException(\RuntimeException::class);
        $svc->register('invalid*user', 'pw1234');
    }

    public function testRegisterThrowsOnInvalidPasswordCharacters()
    {
        $svc = new TestableUserService($this->tmpFile);
        $this->expectException(\RuntimeException::class);
        $svc->register('julia', "pw\n1234");
    }

    public function testRegisterThrowsOnShortUsername()
    {
        $svc = new TestableUserService($this->tmpFile);
        $this->expectException(\RuntimeException::class);
        $svc->register('ab', 'validPassword');
    }

    public function testLoginReturnsUserOnSuccess()
    {
        $svc = new TestableUserService($this->tmpFile);
        $svc->register('carol', 'pw1234');
        $user = $svc->verifyLogin('carol', 'pw1234');
        $this->assertSame('carol', $user['username']);
    }

    public function testLoginThrowsOnWrongPassword()
    {
        $svc = new TestableUserService($this->tmpFile);
        $svc->register('dave', 'pw1234');
        $this->expectException(\RuntimeException::class);
        $svc->verifyLogin('dave', 'wrongpw');
    }

    public function testLoginThrowsOnMissingUser()
    {
        $svc = new TestableUserService($this->tmpFile);
        $this->expectException(\RuntimeException::class);
        $svc->verifyLogin('eve', 'pw1234');
    }

    public function testFindUserReturnsUserOrNull()
    {
        $svc = new TestableUserService($this->tmpFile);
        $svc->register('frank', 'pw1234');
        $user = $svc->findUser('frank');
        $this->assertNotNull($user);
        $this->assertSame('frank', $user['username']);
        $missing = $svc->findUser('ghost');
        $this->assertNull($missing);
    }
}
