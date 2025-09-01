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
}
