<?php

use App\JournalService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JournalService::class)]
class JournalServiceTest extends TestCase
{
    public function testAddEntryStoresAndReturnsData()
    {
        $this->assertTrue(class_exists(\App\JournalService::class));

        $service = new JournalService();
        $entry = $service->addEntry('Session 1', 'We fought a goblin!');

        $this->assertArrayHasKey('id', $entry);
        $this->assertSame('Session 1', $entry['title']);
        $this->assertSame('We fought a goblin!', $entry['body']);
        $this->assertNotEmpty($entry['created_at']);

        $allEntries = $service->getEntries();
        $this->assertCount(1, $allEntries);
        $this->assertSame($entry, $allEntries[0]);
    }
}
