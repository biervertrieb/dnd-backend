<?php

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
