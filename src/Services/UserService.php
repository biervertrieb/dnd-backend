<?php

namespace App\Services;

class UserService
{
    private array $users;
    private string $file;

    public function __construct(string $file = __DIR__ . '/../../data/users.json')
    {
        $this->file = $file;
        if (!is_dir(dirname($this->file))) {
            mkdir(dirname($this->file), 0775, true);
        }
        if (!file_exists($this->file)) {
            file_put_contents($this->file, json_encode([]));
        }
        $this->users = $this->load();
    }

    private function load(): array
    {
        $raw = file_get_contents($this->file);
        return $raw ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : [];
    }

    private function save(): void
    {
        file_put_contents($this->file, json_encode($this->users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function register(string $username, string $password): array
    {
        foreach ($this->users as $user) {
            if ($user['username'] === $username) {
                throw new \RuntimeException('Username already exists');
            }
        }
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $user = [
            'id' => uniqid(),
            'username' => $username,
            'password' => $hashed,
            'created_at' => date('c'),
        ];
        $this->users[] = $user;
        $this->save();
        return $user;
    }

    public function login(string $username, string $password): array
    {
        foreach ($this->users as $user) {
            if ($user['username'] === $username) {
                if (password_verify($password, $user['password'])) {
                    return $user;
                }
                throw new \RuntimeException('Invalid password');
            }
        }
        throw new \RuntimeException('User not found');
    }

    public function findUser(string $username): ?array
    {
        foreach ($this->users as $user) {
            if ($user['username'] === $username) {
                return $user;
            }
        }
        return null;
    }
}
