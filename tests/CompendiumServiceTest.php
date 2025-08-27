<?php

use App\CompendiumService;
use App\Util\Slug;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompendiumService::class)]
class CompendiumServiceTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'compendium_test_');
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
        $svc = new CompendiumService($this->tmpFile);
        $entry = $svc->addEntry('Sword', 'A sharp blade.', 'weapon');
        $this->assertArrayHasKey('id', $entry);
        $this->assertArrayHasKey('slug', $entry);
        $this->assertSame('Sword', $entry['title']);
        $this->assertSame('A sharp blade.', $entry['body']);
        $this->assertSame('weapon', $entry['tags']);
        $this->assertNotEmpty($entry['created_at']);
        $this->assertNotEmpty($entry['updated_at']);
    }

    public function testAddEntryThrowsOnEmptyTitle()
    {
        $svc = new CompendiumService($this->tmpFile);
        $this->expectException(\RuntimeException::class);
        $svc->addEntry('', 'Body', 'tag');
    }

    public function testAddEntryThrowsOnEmptyBody()
    {
        $svc = new CompendiumService($this->tmpFile);
        $this->expectException(\RuntimeException::class);
        $svc->addEntry('Title', '', 'tag');
    }

    public function testSlugIsUnique()
    {
        $svc = new CompendiumService($this->tmpFile);
        $entry1 = $svc->addEntry('Sword', 'First sword.', 'weapon');
        $entry2 = $svc->addEntry('Sword', 'Second sword.', 'weapon');
        $this->assertNotEquals($entry1['slug'], $entry2['slug']);
        $this->assertStringStartsWith('sword', $entry2['slug']);
    }

    public function testUpdateEntryModifiesEntry()
    {
        $svc = new CompendiumService($this->tmpFile);
        $entry = $svc->addEntry('Sword', 'A sharp blade.', 'weapon');
        sleep(1);
        $updated = $svc->updateEntry($entry['id'], 'Axe', 'A heavy axe.', 'tool');
        $this->assertSame('Axe', $updated['title']);
        $this->assertSame('A heavy axe.', $updated['body']);
        $this->assertSame('tool', $updated['tags']);
        $this->assertNotEquals($entry['updated_at'], $updated['updated_at']);
    }

    public function testUpdateEntryThrowsOnMissingEntry()
    {
        $svc = new CompendiumService($this->tmpFile);
        $this->expectException(\RuntimeException::class);
        $svc->updateEntry('nonexistent', 'Title', 'Body', 'tag');
    }

    public function testDeleteEntryArchivesEntry()
    {
        $svc = new CompendiumService($this->tmpFile);
        $entry = $svc->addEntry('Sword', 'A sharp blade.', 'weapon');
        sleep(1);
        $deleted = $svc->deleteEntry($entry['id']);
        $this->assertSame('true', $deleted['archived']);
        $this->assertNotEquals($entry['updated_at'], $deleted['updated_at']);
    }

    public function testDeleteEntryThrowsOnMissingEntry()
    {
        $svc = new CompendiumService($this->tmpFile);
        $this->expectException(\RuntimeException::class);
        $svc->deleteEntry('nonexistent');
    }

    public function testGetEntriesReturnsOnlyUnarchivedByDefault()
    {
        $svc = new CompendiumService($this->tmpFile);
        $entry1 = $svc->addEntry('Sword', 'A sharp blade.', 'weapon');
        $entry2 = $svc->addEntry('Axe', 'A heavy axe.', 'tool');
        $svc->deleteEntry($entry1['id']);
        $entries = $svc->getEntries();
        $this->assertCount(1, $entries);
        $this->assertSame($entry2['id'], $entries[0]['id']);
    }

    public function testGetEntriesReturnsArchivedWhenRequested()
    {
        $svc = new CompendiumService($this->tmpFile);
        $entry1 = $svc->addEntry('Sword', 'A sharp blade.', 'weapon');
        $svc->deleteEntry($entry1['id']);
        $archived = $svc->getEntries(true);
        $this->assertCount(1, $archived);
        $this->assertSame($entry1['id'], $archived[0]['id']);
    }

    public function testGetByIDReturnsEntry()
    {
        $svc = new CompendiumService($this->tmpFile);
        $entry = $svc->addEntry('Sword', 'A sharp blade.', 'weapon');
        $found = $svc->getByID($entry['id']);
        $this->assertSame($entry['id'], $found['id']);
    }

    public function testGetByIDThrowsOnMissingEntry()
    {
        $svc = new CompendiumService($this->tmpFile);
        $this->expectException(\RuntimeException::class);
        $svc->getByID('nonexistent');
    }

    public function testGetBySlugReturnsEntry()
    {
        $svc = new CompendiumService($this->tmpFile);
        $entry = $svc->addEntry('Sword', 'A sharp blade.', 'weapon');
        $found = $svc->getBySlug($entry['slug']);
        $this->assertSame($entry['id'], $found['id']);
    }

    public function testGetBySlugReturnsNullOnMissingSlug()
    {
        $svc = new CompendiumService($this->tmpFile);
        $found = $svc->getBySlug('nonexistent-slug');
        $this->assertNull($found);
    }
}
