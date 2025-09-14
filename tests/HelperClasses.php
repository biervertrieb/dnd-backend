<?php

use App\Services\SessionService;

class TestableJournalService extends \App\Services\JournalService
{
    public function __construct(string $filePath)
    {
        parent::__construct($filePath);
    }
}

class TestableCompendiumService extends \App\Services\CompendiumService
{
    public function __construct(string $filePath)
    {
        parent::__construct($filePath);
    }
}

class TestableUserService extends \App\Services\UserService
{
    public function __construct(string $filePath)
    {
        parent::__construct($filePath);
    }
}

class TestableSessionService extends SessionService
{
    public function __construct(string $filePath)
    {
        parent::__construct($filePath);
    }

    public function expireRT(string $refreshToken): void
    {
        foreach ($this->sessions as $key => &$session) {
            if ($session['rt_hash'] === hash('sha256', $refreshToken)) {
                $session['rt_expires_at'] = time() - 1;
                $this->save();
                break;
            }
        }
    }

    public function expireSession(string $refreshToken): void
    {
        foreach ($this->sessions as $key => &$session) {
            if ($session['rt_hash'] === hash('sha256', $refreshToken)) {
                $session['expires_at'] = time() - 1;
                $this->save();
                break;
            }
        }
    }
}
