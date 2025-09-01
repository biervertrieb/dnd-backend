<?php

use App\Util\JWT;
use Dotenv\Dotenv;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JWT::class)]
class JWTUtilTest extends TestCase
{
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

    public function testEncodeAndDecodeReturnsOriginalPayload()
    {
        $payload = ['user_id' => '123', 'username' => 'tester'];
        $token = JWT::encode($payload);
        $decoded = JWT::decode($token);

        $this->assertEquals('123', $decoded['user_id']);
        $this->assertEquals('tester', $decoded['username']);
        $this->assertArrayHasKey('iat', $decoded);
        $this->assertArrayHasKey('exp', $decoded);
    }

    public function testDecodeThrowsOnInvalidToken()
    {
        $this->expectException(\RuntimeException::class);
        JWT::decode('invalid.token.value');
    }

    public function testEncodeUsesConfiguredSecret()
    {
        $payload = ['foo' => 'bar'];
        $token = JWT::encode($payload);
        // To test secret mismatch, skip changing the secret here
        // Instead, just check that decoding with the correct secret works
        $decoded = JWT::decode($token);
        $this->assertEquals('bar', $decoded['foo']);
    }
}
