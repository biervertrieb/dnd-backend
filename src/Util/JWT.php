<?php

namespace App\Util;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;

final class JWT
{
    private const ALGO = 'HS256';

    private static function getSecret(): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? null;
        if (!$secret) {
            throw new \RuntimeException('JWT secret not set in environment');
        }
        return $secret;
    }

    public static function encode(array $payload): string
    {
        // Add issued at and expiry (e.g., 1 hour) only if not already set
        if (!isset($payload['iat'])) {
            $payload['iat'] = time();
        }
        if (!isset($payload['exp'])) {
            $payload['exp'] = $payload['iat'] + 3600;
        }
        return FirebaseJWT::encode($payload, self::getSecret(), self::ALGO);
    }

    public static function decode(string $token): array
    {
        try {
            $decoded = FirebaseJWT::decode($token, new Key(self::getSecret(), self::ALGO));
            // Convert stdClass to array recursively
            $decodedArr = json_decode(json_encode($decoded), true);
            // Ensure iat and exp are present as integers
            if (!isset($decodedArr['iat']) || !isset($decodedArr['exp'])) {
                throw new \RuntimeException('Token missing iat or exp');
            }
            $decodedArr['iat'] = (int) $decodedArr['iat'];
            $decodedArr['exp'] = (int) $decodedArr['exp'];
            return $decodedArr;
        } catch (\Exception $e) {
            throw new \RuntimeException('Invalid token: ' . $e->getMessage());
        }
    }

    public static function invalidateToken(string $token): void
    {
        $tokenFile = __DIR__ . '/../../data/invalid_tokens.json';
        if (!is_dir(dirname($tokenFile))) {
            mkdir(dirname($tokenFile), 0775, true);
        }
        if (!file_exists($tokenFile)) {
            file_put_contents($tokenFile, json_encode([]));
        }
        $raw = file_get_contents($tokenFile);
        $invalidTokens = $raw ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : [];
        $invalidTokens[] = $token;
        file_put_contents($tokenFile, json_encode($invalidTokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function isTokenInvalidated(string $token): bool
    {
        $tokenFile = __DIR__ . '/../../data/invalid_tokens.json';
        if (!file_exists($tokenFile)) {
            return false;
        }
        $raw = file_get_contents($tokenFile);
        $invalidTokens = $raw ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : [];
        return in_array($token, $invalidTokens, true);
    }
}
