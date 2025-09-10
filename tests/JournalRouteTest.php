<?php

use App\Services\JournalService;
use App\Util\JWT;
use Dotenv\Dotenv;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\App;

require_once __DIR__ . '/../src/Routes/JournalRoute.php';
require_once __DIR__ . '/HelperClasses.php';

#[CoversFunction('registerJournalRoutes')]
class JournalRouteTest extends TestCase
{
    private string $tmpFile;
    private JournalService $service;
    private App $app;
    private string $testtoken;

    public static function setUpBeforeClass(): void
    {
        $envPath = __DIR__ . '/../.env';
        if (!file_exists($envPath)) {
            throw new \RuntimeException(".env file not found at $envPath");
        }
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();
        if (empty($_ENV['JWT_SECRET'] ?? null)) {
            throw new \RuntimeException('JWT_SECRET not set in .env file');
        }
    }

    protected function setUp(): void
    {
        $this->testtoken = JWT::encode(['id' => 1, 'username' => 'testuser']);

        $this->tmpFile = tempnam(sys_get_temp_dir(), 'journal_test_');
        file_put_contents($this->tmpFile, json_encode([]));
        $this->service = new TestableJournalService($this->tmpFile);
        // Pre-populate with some entries
        $this->service->addEntry('Session 1', 'We fought a goblin!', 1);
        $this->service->addEntry('Session 2', 'We found a treasure!', 2);

        $this->app = AppFactory::create(new ResponseFactory());
        registerJournalRoutes($this->app, $this->service);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testGetEntriesRoute()
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/journal')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken);
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertIsArray($data);
        $this->assertCount(2, $data);
        $this->assertEquals('Session 2', $data[0]['title']);
        $this->assertEquals('Session 1', $data[1]['title']);
    }

    public function testAddEntryRoute()
    {
        $newEntry = [
            'title' => 'Session 3',
            'body' => 'We met a dragon!',
            'day' => 3
        ];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/journal')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertIsArray($data);
        $this->assertEquals('Session 3', $data['entry']['title']);
        $this->assertEquals('We met a dragon!', $data['entry']['body']);
        $this->assertEquals(3, $data['entry']['day']);

        // Verify it was added
        $entries = $this->service->getEntries();
        $this->assertCount(3, $entries);
    }

    public function testAddEntryRouteTitleValidationError()
    {
        $invalidEntry = [
            'title' => '',
            'body' => 'No title here',
            'day' => 4
        ];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/journal')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($invalidEntry);
        $response = $this->app->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertIsArray($data);
        $this->assertEquals('error', $data['status']);
        $this->assertStringContainsString('Title is empty', $data['message']);
    }

    public function testAddEntryRouteDayValidationError()
    {
        $invalidEntry = [
            'title' => 'Invalid Day',
            'body' => 'This entry has an invalid day',
            'day' => 'a'
        ];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/journal')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($invalidEntry);
        $response = $this->app->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertIsArray($data);
        $this->assertEquals('error', $data['status']);
        $this->assertStringContainsString('Day must be an integer', $data['message']);
    }

    public function testAddEntryRouteBodyValidationError()
    {
        $invalidEntry = [
            'title' => 'No Body',
            'body' => '',
            'day' => 5
        ];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/journal')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($invalidEntry);
        $response = $this->app->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertIsArray($data);
        $this->assertEquals('error', $data['status']);
        $this->assertStringContainsString('Body is empty', $data['message']);
    }

    public function testUpdateEntryRoute()
    {
        $entries = $this->service->getEntries();
        $entryToUpdate = $entries[0];
        $updatedData = [
            'title' => 'Updated Session 2',
            'body' => 'We found an even bigger treasure!',
            'day' => 2
        ];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', '/journal/' . $entryToUpdate['id'])
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($updatedData);
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertIsArray($data);
        $this->assertEquals('Updated Session 2', $data['entry']['title']);
        $this->assertEquals('We found an even bigger treasure!', $data['entry']['body']);

        // Verify it was updated
        $updatedEntry = $this->service->getEntries()[0];
        $this->assertEquals('Updated Session 2', $updatedEntry['title']);
    }

    public function testUpdateEntryRouteTitleValidationError()
    {
        $entries = $this->service->getEntries();
        $entryToUpdate = $entries[0];
        $invalidData = [
            'title' => '',
            'body' => 'This body is fine',
            'day' => 2
        ];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', '/journal/' . $entryToUpdate['id'])
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($invalidData);
        $response = $this->app->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertIsArray($data);
        $this->assertEquals('error', $data['status']);
        $this->assertStringContainsString('Title is empty', $data['message']);
    }

    public function testUpdateEntryRouteDayValidationError()
    {
        $entries = $this->service->getEntries();
        $entryToUpdate = $entries[0];
        $invalidData = [
            'title' => 'Valid Title',
            'body' => 'This body is fine',
            'day' => 'notanumber'
        ];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', '/journal/' . $entryToUpdate['id'])
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($invalidData);
        $response = $this->app->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertIsArray($data);
        $this->assertEquals('error', $data['status']);
        $this->assertStringContainsString('Day must be an integer', $data['message']);
    }

    public function testUpdateEntryRouteBodyValidationError()
    {
        $entries = $this->service->getEntries();
        $entryToUpdate = $entries[0];
        $invalidData = [
            'title' => 'Valid Title',
            'body' => '',
            'day' => 2
        ];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', '/journal/' . $entryToUpdate['id'])
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($invalidData);
        $response = $this->app->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertIsArray($data);
        $this->assertEquals('error', $data['status']);
        $this->assertStringContainsString('Body is empty', $data['message']);
    }

    public function testDeleteEntryRoute()
    {
        $entries = $this->service->getEntries();
        $entryToDelete = $entries[0];
        $request = (new ServerRequestFactory())
            ->createServerRequest('DELETE', '/journal/' . $entryToDelete['id'])
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken);
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertIsArray($data);
        $this->assertEquals('ok', $data['status']);
        $this->assertEquals('true', $data['entry']['archived']);

        // Verify it was archived
        $remainingEntries = $this->service->getEntries();
        $this->assertCount(1, $remainingEntries);
        $this->assertNotEquals($entryToDelete['id'], $remainingEntries[0]['id']);
    }

    public function testDeleteEntryRouteNonexistentEntry()
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('DELETE', '/journal/nonexistent-id')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken);
        $response = $this->app->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertIsArray($data);
        $this->assertEquals('error', $data['status']);
        $this->assertStringContainsString('Entry not found', $data['message']);
    }

    public function testGetEntryUnauthorizedAccess()
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/journal');
        $response = $this->app->handle($request);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAddEntryUnauthorizedAccess()
    {
        $newEntry = [
            'title' => 'Session 3',
            'body' => 'We met a dragon!',
            'day' => 3
        ];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/journal')
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testUpdateEntryUnauthorizedAccess()
    {
        $entries = $this->service->getEntries();
        $entryToUpdate = $entries[0];
        $updatedData = [
            'title' => 'Updated Session 2',
            'body' => 'We found an even bigger treasure!',
            'day' => 2
        ];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', '/journal/' . $entryToUpdate['id'])
            ->withParsedBody($updatedData);
        $response = $this->app->handle($request);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDeleteEntryUnauthorizedAccess()
    {
        $entries = $this->service->getEntries();
        $entryToDelete = $entries[0];
        $request = (new ServerRequestFactory())
            ->createServerRequest('DELETE', '/journal/' . $entryToDelete['id']);
        $response = $this->app->handle($request);

        $this->assertEquals(401, $response->getStatusCode());
    }
}
