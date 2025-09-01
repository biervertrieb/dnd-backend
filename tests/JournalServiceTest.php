<?php

use App\Services\JournalService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JournalService::class)]
class JournalServiceTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'journal_test_');
        file_put_contents($this->tmpFile, json_encode([]));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testAddEntryStoresAndReturnsData()
    {
        $this->assertTrue(class_exists(\App\Services\JournalService::class));
        $service = new JournalService($this->tmpFile);
        $entry = $service->addEntry('Session 1', 'We fought a goblin!', 1);
        $this->assertArrayHasKey('id', $entry);
        $this->assertSame('Session 1', $entry['title']);
        $this->assertSame('We fought a goblin!', $entry['body']);
        $this->assertNotEmpty($entry['created_at']);
    }

    public function testAddEntryThrowsOnEmptyTitle()
    {
        $service = new JournalService($this->tmpFile);
        $this->expectException(\RuntimeException::class);
        $service->addEntry('', 'Body', 1);
    }

    public function testAddEntryThrowsOnEmptyBody()
    {
        $service = new JournalService($this->tmpFile);
        $this->expectException(\RuntimeException::class);
        $service->addEntry('Title', '', 1);
    }

    public function testAddEntryThrowsOnEmptyDay()
    {
        $service = new JournalService($this->tmpFile);
        $this->expectException(\TypeError::class);
        $service->addEntry('Title', 'Body', null);
    }

    public function testUpdateEntryModifiesEntry()
    {
        $service = new JournalService($this->tmpFile);
        $entry = $service->addEntry('Old', 'OldBody', 1);
        sleep(1);
        $updated = $service->updateEntry($entry['id'], 'New', 'NewBody', 2);
        $this->assertSame('New', $updated['title']);
        $this->assertSame('NewBody', $updated['body']);
        $this->assertSame(2, $updated['day']);
        $this->assertNotEquals($entry['updated_at'], $updated['updated_at']);
    }

    public function testUpdateEntryThrowsOnMissingEntry()
    {
        $service = new JournalService($this->tmpFile);
        $this->expectException(\RuntimeException::class);
        $service->updateEntry('nonexistent', 'Title', 'Body', 1);
    }

    public function testDeleteEntryArchivesEntry()
    {
        $service = new JournalService($this->tmpFile);
        $entry = $service->addEntry('Title', 'Body', 1);
        sleep(1);
        $deleted = $service->deleteEntry($entry['id']);
        $this->assertSame('true', $deleted['archived']);
        $this->assertNotEquals($entry['updated_at'], $deleted['updated_at']);
    }

    public function testDeleteEntryThrowsOnMissingEntry()
    {
        $service = new JournalService($this->tmpFile);
        $this->expectException(\RuntimeException::class);
        $service->deleteEntry('nonexistent');
    }

    public function testGetEntriesReturnsOnlyUnarchivedByDefault()
    {
        $service = new JournalService($this->tmpFile);
        $entry1 = $service->addEntry('Title1', 'Body1', 1);
        $entry2 = $service->addEntry('Title2', 'Body2', 2);
        $service->deleteEntry($entry1['id']);
        $entries = $service->getEntries();
        $this->assertCount(1, $entries);
        $this->assertSame($entry2['id'], $entries[0]['id']);
    }

    public function testGetEntriesReturnsArchivedWhenRequested()
    {
        $service = new JournalService($this->tmpFile);
        $entry1 = $service->addEntry('Title1', 'Body1', 1);
        $service->deleteEntry($entry1['id']);
        $archived = $service->getEntries(true);
        $this->assertCount(1, $archived);
        $this->assertSame($entry1['id'], $archived[0]['id']);
    }
}
