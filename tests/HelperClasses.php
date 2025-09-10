<?php

class TestableJournalService extends \App\Services\JournalService
{
    public function __construct(string $filePath)
    {
        parent::__construct($filePath);
    }
}
