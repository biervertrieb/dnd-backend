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

    public function testAddEntryMalformedJson(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/journal')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withHeader('Content-Type', 'application/json');
        $body = '{"title": "Malformed", "body": "Missing end brace", "day": 1';
        $request = $request->withBody((new \Slim\Psr7\Stream(fopen('php://temp', 'r+'))));
        $request->getBody()->write($body);
        $request->getBody()->rewind();
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAddEntryWrongContentType(): void
    {
        $newEntry = ['title' => 'Session', 'body' => 'Body', 'day' => 1];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/journal')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withHeader('Content-Type', 'text/plain')
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAddEntryPayloadTooLarge(): void
    {
        $largeBody = str_repeat('A', 1024 * 1024);  // 1MB
        $newEntry = ['title' => 'Big', 'body' => $largeBody, 'day' => 1];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/journal')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAddEntryTitleTooLong(): void
    {
        $longTitle = str_repeat('T', 256);
        $newEntry = ['title' => $longTitle, 'body' => 'Valid body', 'day' => 1];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/journal')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAddEntryBodyTooLong(): void
    {
        $longBody = str_repeat('B', 10001);
        $newEntry = ['title' => 'Valid', 'body' => $longBody, 'day' => 1];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/journal')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAddEntryInvalidToken(): void
    {
        $newEntry = ['title' => 'Session', 'body' => 'Body', 'day' => 1];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/journal')
            ->withHeader('Authorization', 'Bearer invalidtoken')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testUpdateEntryMalformedJson(): void
    {
        $entries = $this->service->getEntries();
        $entryToUpdate = $entries[0];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', '/journal/' . $entryToUpdate['id'])
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withHeader('Content-Type', 'application/json');
        $body = '{"title": "Malformed", "body": "Missing end brace", "day": 1';
        $request = $request->withBody((new \Slim\Psr7\Stream(fopen('php://temp', 'r+'))));
        $request->getBody()->write($body);
        $request->getBody()->rewind();
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUpdateEntryWrongContentType(): void
    {
        $entries = $this->service->getEntries();
        $entryToUpdate = $entries[0];
        $updatedData = ['title' => 'Session', 'body' => 'Body', 'day' => 1];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', '/journal/' . $entryToUpdate['id'])
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withHeader('Content-Type', 'text/plain')
            ->withParsedBody($updatedData);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUpdateEntryPayloadTooLarge(): void
    {
        $entries = $this->service->getEntries();
        $entryToUpdate = $entries[0];
        $largeBody = str_repeat('A', 1024 * 1024);  // 1MB
        $updatedData = ['title' => 'Big', 'body' => $largeBody, 'day' => 1];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', '/journal/' . $entryToUpdate['id'])
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody($updatedData);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUpdateEntryTitleTooLong(): void
    {
        $entries = $this->service->getEntries();
        $entryToUpdate = $entries[0];
        $longTitle = str_repeat('T', 256);
        $updatedData = ['title' => $longTitle, 'body' => 'Valid body', 'day' => 1];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', '/journal/' . $entryToUpdate['id'])
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody($updatedData);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUpdateEntryBodyTooLong(): void
    {
        $entries = $this->service->getEntries();
        $entryToUpdate = $entries[0];
        $longBody = str_repeat('B', 10001);
        $updatedData = ['title' => 'Valid', 'body' => $longBody, 'day' => 1];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', '/journal/' . $entryToUpdate['id'])
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody($updatedData);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUpdateEntryInvalidToken(): void
    {
        $entries = $this->service->getEntries();
        $entryToUpdate = $entries[0];
        $updatedData = ['title' => 'Session', 'body' => 'Body', 'day' => 1];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', '/journal/' . $entryToUpdate['id'])
            ->withHeader('Authorization', 'Bearer invalidtoken')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody($updatedData);
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
    }

    // --- Edge Cases ---

    public function testAddEntryEmptyBody(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/journal')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAddEntryBodyNotObject(): void
    {
        // Array
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/journal')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody(['not', 'an', 'object']);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());

        // String
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/journal')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody('justastring');
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());

        // Number
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/journal')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody(123);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAddEntryExtraFields(): void
    {
        $newEntry = ['title' => 'Extra', 'body' => 'Has extra fields.', 'day' => 1, 'extra' => 'unexpected'];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/journal')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('ok', $data['status']);
        $this->assertEquals('Extra', $data['entry']['title']);
    }

    public function testAddEntryDuplicateKeys(): void
    {
        $rawJson = '{"title": "Session", "title": "Duplicate", "body": "Body", "day": 1}';
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/journal')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken);
        $stream = new \Slim\Psr7\Stream(fopen('php://temp', 'r+'));
        $stream->write($rawJson);
        $stream->rewind();
        $request = $request->withBody($stream);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('Duplicate', $data['entry']['title']);
    }

    public function testAddEntryBooleanNumericTypes(): void
    {
        $newEntry = ['title' => true, 'body' => 123, 'day' => false];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/journal')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAddEntryWhitespaceOnlyStrings(): void
    {
        $newEntry = ['title' => '   ', 'body' => "\t\n", 'day' => 1];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/journal')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAddEntryBoundaryValues(): void
    {
        $boundaryTitle = str_repeat('T', 255);
        $boundaryBody = str_repeat('B', 10000);
        $boundaryDay = PHP_INT_MAX;
        $newEntry = ['title' => $boundaryTitle, 'body' => $boundaryBody, 'day' => $boundaryDay];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/journal')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals($boundaryTitle, $data['entry']['title']);
        $this->assertEquals($boundaryBody, $data['entry']['body']);
        $this->assertEquals($boundaryDay, $data['entry']['day']);
    }

    public function testAddEntrySpecialUnicodeChars(): void
    {
        $newEntry = ['title' => 'Tést 🚀', 'body' => 'Bödy 💡', 'day' => 1];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/journal')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('Tést 🚀', $data['entry']['title']);
        $this->assertEquals('Bödy 💡', $data['entry']['body']);
    }

    public function testUnsupportedHttpMethods(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('PATCH', '/journal')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken);
        $response = $this->app->handle($request);
        $this->assertTrue(in_array($response->getStatusCode(), [404, 405]));

        $request = (new ServerRequestFactory())
            ->createServerRequest('OPTIONS', '/journal')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken);
        $response = $this->app->handle($request);
        $this->assertTrue(in_array($response->getStatusCode(), [200, 404, 405]));

        $request = (new ServerRequestFactory())
            ->createServerRequest('HEAD', '/journal')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken);
        $response = $this->app->handle($request);
        $this->assertTrue(in_array($response->getStatusCode(), [200, 404, 405]));
    }

    public function testAddEntryDuplicateTitle(): void
    {
        $newEntry1 = ['title' => 'Unique', 'body' => 'Body', 'day' => 1];
        $newEntry2 = ['title' => 'Unique', 'body' => 'Body2', 'day' => 2];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/journal')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry1);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data1 = json_decode((string) $response->getBody(), true);
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/journal')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry2);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data2 = json_decode((string) $response->getBody(), true);
        $this->assertNotEquals($data1['entry']['id'], $data2['entry']['id']);
    }

    public function testDeleteAlreadyArchivedEntry(): void
    {
        $entry = $this->service->getEntries()[0];
        $id = $entry['id'];
        // Archive
        $request = (new ServerRequestFactory())
            ->createServerRequest('DELETE', "/journal/{$id}")
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        // Try again
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUpdateDeleteArchivedEntry(): void
    {
        $entry = $this->service->getEntries()[0];
        $id = $entry['id'];
        // Archive
        $request = (new ServerRequestFactory())
            ->createServerRequest('DELETE', "/journal/{$id}")
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken);
        $this->app->handle($request);
        // Try update
        $updatedData = ['title' => 'Archived', 'body' => 'Archived', 'day' => 1];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', "/journal/{$id}")
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($updatedData);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAuthorizationHeaderWrongScheme(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/journal')
            ->withHeader('Authorization', 'Basic ' . $this->testtoken);
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testMalformedJwtToken(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/journal')
            ->withHeader('Authorization', 'Bearer malformed.token');
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
    }
}
