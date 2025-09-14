<?php

namespace App\Services;

use App\Util\JWT;

class SessionService extends \App\Util\Singleton
{
    protected array $sessions;
    protected string $file;

    protected function __construct(string $file = __DIR__ . '/../../data/sessions.json')
    {
        $this->file = $file;
        if (!is_dir(dirname($this->file))) {
            mkdir(dirname($this->file), 0775, true);
        }
        if (!file_exists($this->file)) {
            file_put_contents($this->file, json_encode([]));
        }
        $raw = file_get_contents($this->file);
        $this->sessions = $raw ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : [];
    }

    protected function save(): void
    {
        file_put_contents($this->file, json_encode($this->sessions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private static function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Creates a new session for a user.
     * @param int $userId The ID of the user.
     * @param string $username The username of the user.
     * @return array ['refreshToken' => string, 'accessToken' => string]
     * @throws \TypeError If the types of the parameters are incorrect.
     * @throws \RuntimeException If the parameters are invalid.
     */
    public function createSession(int $userId, string $username): array
    {
        // Type check
        if (!is_int($userId)) {
            throw new \TypeError('User ID must be an integer');
        }
        // Validation
        if ($userId <= 0) {
            throw new \RuntimeException('Invalid user ID');
        }
        if (!is_string($username)) {
            throw new \TypeError('Username must be a string');
        }
        $username = trim($username);
        if ($username === '') {
            throw new \RuntimeException('Username cannot be empty');
        }
        // Create session ID
        $sessID = uniqid('sess_', true);
        $refreshToken = SessionService::generateToken();
        $accessToken = JWT::encode([
            'id' => $userId,
            'username' => $username
        ]);

        // Store session
        $this->sessions[$sessID] = [
            'user_id' => $userId,
            'username' => $username,
            'created_at' => time(),
            'last_activity' => time(),
            'expires_at' => time() + 60 * 60 * 24 * 30,  // 30 days
            'rt_hash' => hash('sha256', $refreshToken),
            'rt_expires_at' => time() + 60 * 60 * 24 * 7,  // 7 days
            'previousTokens' => [],
        ];
        $this->save();
        return ['refreshToken' => $refreshToken, 'accessToken' => $accessToken];
    }

    /**
     * Refreshes a session using a refresh token.
     * @param string $refreshToken The refresh token.
     * @return array ['refreshToken' => string, 'accessToken' => string, 'user_id' => int, 'username' => string]
     * @throws \TypeError If the type of the parameter is incorrect.
     * @throws \RuntimeException If the token is invalid or expired.
     */
    public function refreshSession(string $refreshToken): array
    {
        // Type check
        if (!is_string($refreshToken)) {
            throw new \TypeError('Refresh token must be a string');
        }

        foreach ($this->sessions as $key => &$session) {
            if (hash('sha256', $refreshToken) === $session['rt_hash']) {
                // token found, check expiry
                if ($session['expires_at'] < time() || $session['rt_expires_at'] < time()) {
                    unset($this->sessions[$key]);
                    $this->save();
                    Throw new \RuntimeException('Session expired');
                }

                // update session
                $session['last_activity'] = time();
                // move current token to previous tokens
                $session['previousTokens'][] = $session['rt_hash'];

                // generate new refresh token
                $newRefreshToken = SessionService::generateToken();
                $session['rt_hash'] = hash('sha256', $newRefreshToken);
                $session['rt_expires_at'] = time() + 60 * 60 * 24 * 7;  // 7 days
                $this->save();
                $accessToken = JWT::encode([
                    'id' => $session['user_id'],
                    'username' => $session['username']
                ]);
                return [
                    'user_id' => intval($session['user_id']),
                    'username' => $session['username'],
                    'refreshToken' => $newRefreshToken,
                    'accessToken' => $accessToken
                ];
                break;
            }
            if (in_array(hash('sha256', $refreshToken), $session['previousTokens'], true)) {
                // token found in previous tokens, possible reuse attack
                unset($this->sessions[$key]);
                $this->save();
                throw new \RuntimeException('Refresh token reuse detected. Session invalidated.');
            }
        }
        // token was not found
        throw new \RuntimeException('Invalid refresh token');
    }

    /**
     * Invalidates a session using a refresh token.
     * @param string $refreshToken The refresh token.
     * @return void
     * @throws \TypeError If the type of the parameter is incorrect.
     */
    public function invalidateSession(string $refreshToken): void
    {
        // Type check
        if (!is_string($refreshToken)) {
            throw new \TypeError('Refresh token must be a string');
        }

        foreach ($this->sessions as $key => $session) {
            if (hash('sha256', $refreshToken) === $session['rt_hash'] ||
                    in_array(hash('sha256', $refreshToken), $session['previousTokens'], true)) {
                unset($this->sessions[$key]);
                $this->save();
                return;
            }
        }
        // token was not found, nothing to invalidate
        return;
    }
}
