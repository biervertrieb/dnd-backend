<?php

use App\Services\CompendiumService;
use App\Util\JWT;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\App;

require_once __DIR__ . '/../src/Routes/CompendiumRoute.php';
require_once __DIR__ . '/HelperClasses.php';

#[CoversFunction('registerCompendiumRoutes')]
class CompendiumRouteTest extends TestCase
{
    private string $tmpFile;
    private CompendiumService $service;
    private App $app;
    private string $testtoken;

    public static function setUpBeforeClass(): void
    {
        $envPath = __DIR__ . '/../.env';
        if (!file_exists($envPath)) {
            throw new \RuntimeException(".env file not found at $envPath");
        }
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();
        if (empty($_ENV['JWT_SECRET'] ?? null)) {
            throw new \RuntimeException('JWT_SECRET not set in .env file');
        }
    }

    protected function setUp(): void
    {
        $this->testtoken = JWT::encode(['id' => 1, 'username' => 'testuser']);

        $this->tmpFile = tempnam(sys_get_temp_dir(), 'compendium_test_');
        file_put_contents($this->tmpFile, json_encode([]));
        $this->service = new TestableCompendiumService($this->tmpFile);

        $this->service->addEntry('Sword', 'A sharp blade.', ['weapon']);
        $this->service->addEntry('Shield', 'A sturdy shield.', ['armor']);

        $this->app = AppFactory::create(new ResponseFactory());

        registerCompendiumRoutes($this->app, $this->service);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testGetAllEntries(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/compendium')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken);

        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertIsArray($data);
        $this->assertCount(2, $data);
        $this->assertEquals('Shield', $data[0]['title']);
        $this->assertEquals('Sword', $data[1]['title']);
    }

    public function testGetEntryBySlug(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/compendium/sword')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken);

        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals('ok', $data['status']);
        $this->assertEquals('Sword', $data['entry']['title']);
    }

    public function testGetEntryById(): void
    {
        $entry = $this->service->getBySlug('sword');
        $this->assertNotNull($entry);
        $id = $entry['id'];

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', "/compendium/{$id}")
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken);

        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals('ok', $data['status']);
        $this->assertEquals('Sword', $data['entry']['title']);
    }

    public function testGetEntryNotFound(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/compendium/nonexistent')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken);

        $response = $this->app->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals('error', $data['status']);
    }

    public function testAddEntry(): void
    {
        $newEntry = ['title' => 'Bow', 'body' => 'A ranged weapon.', 'tags' => ['weapon', 'ranged']];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/compendium')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry);

        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals('ok', $data['status']);
        $this->assertEquals('Bow', $data['entry']['title']);

        // Verify it was added
        $allEntries = $this->service->getEntries();
        $this->assertCount(3, $allEntries);
    }

    public function testAddEntryMissingTitle(): void
    {
        $newEntry = ['body' => 'No title here.', 'tags' => []];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/compendium')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry);

        $response = $this->app->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals('error', $data['status']);
    }

    public function testAddEntryMissingBody(): void
    {
        $newEntry = ['title' => 'No Body', 'tags' => []];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/compendium')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry);

        $response = $this->app->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals('error', $data['status']);
    }

    public function testAddEntryWrongTagsType(): void
    {
        $newEntry = ['title' => 'Bad Tags', 'body' => 'Tags should be an array.', 'tags' => 'not-an-array'];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/compendium')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry);

        $response = $this->app->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals('error', $data['status']);
    }

    public function testAddEntryMalformedJson(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/compendium')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken);

        // Simulate malformed JSON by overriding the body
        $body = '{"title": "Malformed", "body": "Missing end brace"';
        $request = $request->withBody((new \Slim\Psr7\Stream(fopen('php://temp', 'r+'))));
        $request->getBody()->write($body);
        $request->getBody()->rewind();

        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('error', $data['status']);
    }

    public function testAddEntryEmptyBody(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/compendium')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken);

        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('error', $data['status']);
    }

    public function testAddEntryExtraFields(): void
    {
        $newEntry = ['title' => 'Extra', 'body' => 'Has extra fields.', 'tags' => ['misc'], 'extra' => 'unexpected'];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/compendium')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry);

        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('ok', $data['status']);
        $this->assertEquals('Extra', $data['entry']['title']);
    }

    public function testAddEntryNullValues(): void
    {
        $newEntry = ['title' => null, 'body' => null, 'tags' => null];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/compendium')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry);

        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('error', $data['status']);
    }

    public function testAddEntryWrongContentType(): void
    {
        $newEntry = ['title' => 'Wrong Content-Type', 'body' => 'Should fail.', 'tags' => ['fail']];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/compendium')
            ->withHeader('Content-Type', 'text/plain')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry);

        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('error', $data['status']);
    }

    public function testUpdateEntry(): void
    {
        $entry = $this->service->getBySlug('sword');
        $this->assertNotNull($entry);
        $id = $entry['id'];

        $updatedData = ['title' => 'Longsword', 'body' => 'A longer sharp blade.', 'tags' => ['weapon', 'melee']];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', "/compendium/{$id}")
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($updatedData);

        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertEquals('ok', $data['status']);
        $this->assertEquals('Longsword', $data['entry']['title']);

        // Verify it was updated
        $updatedEntry = $this->service->getByID($id);
        $this->assertEquals('Longsword', $updatedEntry['title']);
    }

    public function testUpdateEntryMalformedJson(): void
    {
        $entry = $this->service->getBySlug('sword');
        $id = $entry['id'];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', "/compendium/{$id}")
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken);
        $body = '{"title": "Bad", "body": "Missing end brace"';
        $request = $request->withBody((new \Slim\Psr7\Stream(fopen('php://temp', 'r+'))));
        $request->getBody()->write($body);
        $request->getBody()->rewind();
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('error', $data['status']);
    }

    public function testUpdateEntryMissingTitle(): void
    {
        $entry = $this->service->getBySlug('sword');
        $id = $entry['id'];
        $updatedData = ['body' => 'No title here.', 'tags' => []];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', "/compendium/{$id}")
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($updatedData);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('error', $data['status']);
    }

    public function testUpdateEntryMissingBody(): void
    {
        $entry = $this->service->getBySlug('sword');
        $id = $entry['id'];
        $updatedData = ['title' => 'No Body', 'tags' => []];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', "/compendium/{$id}")
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($updatedData);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('error', $data['status']);
    }

    public function testUpdateEntryWrongTagsType(): void
    {
        $entry = $this->service->getBySlug('sword');
        $id = $entry['id'];
        $updatedData = ['title' => 'Bad Tags', 'body' => 'Tags should be an array.', 'tags' => 'not-an-array'];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', "/compendium/{$id}")
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($updatedData);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('error', $data['status']);
    }

    public function testUpdateEntryNullValues(): void
    {
        $entry = $this->service->getBySlug('sword');
        $id = $entry['id'];
        $updatedData = ['title' => null, 'body' => null, 'tags' => null];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', "/compendium/{$id}")
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($updatedData);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('error', $data['status']);
    }

    public function testUpdateEntryNotFound(): void
    {
        $updatedData = ['title' => 'Ghost', 'body' => 'Not found.', 'tags' => []];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', '/compendium/nonexistent')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($updatedData);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('error', $data['status']);
    }

    public function testUpdateEntryWrongContentType(): void
    {
        $entry = $this->service->getBySlug('sword');
        $id = $entry['id'];
        $updatedData = ['title' => 'Wrong Content-Type', 'body' => 'Should fail.', 'tags' => ['fail']];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', "/compendium/{$id}")
            ->withHeader('Content-Type', 'text/plain')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($updatedData);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('error', $data['status']);
    }

    public function testDeleteEntry(): void
    {
        $entry = $this->service->getBySlug('sword');
        $id = $entry['id'];
        $request = (new ServerRequestFactory())
            ->createServerRequest('DELETE', "/compendium/{$id}")
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('ok', $data['status']);
        $this->assertEquals('true', $data['entry']['archived']);
    }

    public function testDeleteEntryNotFound(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('DELETE', '/compendium/nonexistent')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('error', $data['status']);
    }

    public function testDeleteEntryWrongContentType(): void
    {
        $entry = $this->service->getBySlug('shield');
        $id = $entry['id'];
        $request = (new ServerRequestFactory())
            ->createServerRequest('DELETE', "/compendium/{$id}")
            ->withHeader('Content-Type', 'text/plain')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());  // DELETE does not parse body, so should succeed
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('ok', $data['status']);
    }

    public function testGetAllEntriesMissingAuth(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/compendium');
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetEntryBySlugMissingAuth(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/compendium/sword');
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAddEntryMissingAuth(): void
    {
        $newEntry = ['title' => 'Bow', 'body' => 'A ranged weapon.', 'tags' => ['weapon', 'ranged']];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/compendium')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testUpdateEntryMissingAuth(): void
    {
        $entry = $this->service->getBySlug('sword');
        $id = $entry['id'];
        $updatedData = ['title' => 'Longsword', 'body' => 'A longer sharp blade.', 'tags' => ['weapon', 'melee']];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', "/compendium/{$id}")
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody($updatedData);
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDeleteEntryMissingAuth(): void
    {
        $entry = $this->service->getBySlug('sword');
        $id = $entry['id'];
        $request = (new ServerRequestFactory())
            ->createServerRequest('DELETE', "/compendium/{$id}");
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetAllEntriesInvalidToken(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/compendium')
            ->withHeader('Authorization', 'Bearer invalidtoken');
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetEntryBySlugInvalidToken(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/compendium/sword')
            ->withHeader('Authorization', 'Bearer invalidtoken');
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAddEntryInvalidToken(): void
    {
        $newEntry = ['title' => 'Bow', 'body' => 'A ranged weapon.', 'tags' => ['weapon', 'ranged']];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/compendium')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer invalidtoken')
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testUpdateEntryInvalidToken(): void
    {
        $entry = $this->service->getBySlug('sword');
        $id = $entry['id'];
        $updatedData = ['title' => 'Longsword', 'body' => 'A longer sharp blade.', 'tags' => ['weapon', 'melee']];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', "/compendium/{$id}")
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer invalidtoken')
            ->withParsedBody($updatedData);
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
    }

    // --- Edge Cases ---

    public function testAddEntryBooleanNumericTypes(): void
    {
        $newEntry = ['title' => true, 'body' => 123, 'tags' => [false, 456]];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/compendium')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAddEntryWhitespaceOnlyStrings(): void
    {
        $newEntry = ['title' => '   ', 'body' => "\t\n", 'tags' => ['   ', "\n"]];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/compendium')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAddEntryTagsContainNonString(): void
    {
        $newEntry = ['title' => 'Valid Title', 'body' => 'Valid body.', 'tags' => ['valid', 123, null]];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/compendium')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAddEntryBoundaryValues(): void
    {
        $newEntry = ['title' => str_repeat('A', 255), 'body' => str_repeat('B', 10000), 'tags' => ['tag1', 'tag2']];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/compendium')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('ok', $data['status']);
        $this->assertEquals(str_repeat('A', 255), $data['entry']['title']);
    }

    public function testAddEntrySpecialUnicodeChars(): void
    {
        $newEntry = ['title' => '特殊字符', 'body' => 'Emoji 😊 and accents éàü', 'tags' => ['标签', 'emoji']];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/compendium')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('ok', $data['status']);
        $this->assertEquals('特殊字符', $data['entry']['title']);
    }

    public function testAddEntryDuplicateTitleSlug(): void
    {
        $newEntry1 = ['title' => 'Unique', 'body' => 'Body', 'tags' => ['tag']];
        $newEntry2 = ['title' => 'Unique', 'body' => 'Body2', 'tags' => ['tag2']];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/compendium')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry1);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data1 = json_decode((string) $response->getBody(), true);
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/compendium')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry2);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data2 = json_decode((string) $response->getBody(), true);
        $this->assertNotEquals($data1['entry']['slug'], $data2['entry']['slug']);
    }

    public function testDeleteAlreadyArchivedEntry(): void
    {
        $entry = $this->service->getEntries()[0];
        $id = $entry['id'];
        // Archive
        $request = (new ServerRequestFactory())
            ->createServerRequest('DELETE', "/compendium/{$id}")
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        // Try again
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());  // Should still succeed, idempotent
    }

    public function testUpdateDeleteArchivedEntry(): void
    {
        $entry = $this->service->getEntries()[0];
        $id = $entry['id'];
        // Archive
        $request = (new ServerRequestFactory())
            ->createServerRequest('DELETE', "/compendium/{$id}")
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken);
        $this->app->handle($request);
        // Try update
        $updatedData = ['title' => 'Archived', 'body' => 'Archived', 'tags' => ['archived']];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', "/compendium/{$id}")
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($updatedData);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());  // Service allows update
    }

    public function testAuthorizationHeaderWrongScheme(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/compendium')
            ->withHeader('Authorization', 'Basic ' . $this->testtoken);
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testMalformedJwtToken(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/compendium')
            ->withHeader('Authorization', 'Bearer malformed.token');
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDeleteEntryInvalidToken(): void
    {
        $entry = $this->service->getBySlug('sword');
        $id = $entry['id'];
        $request = (new ServerRequestFactory())
            ->createServerRequest('DELETE', "/compendium/{$id}")
            ->withHeader('Authorization', 'Bearer invalidtoken');
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAddEntryPayloadTooLarge(): void
    {
        $largeBody = str_repeat('A', 1024 * 1024);  // 1MB
        $newEntry = ['title' => 'Big', 'body' => $largeBody, 'tags' => ['weapon']];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/compendium')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('error', $data['status']);
    }

    public function testAddEntryTitleTooLong(): void
    {
        $longTitle = str_repeat('T', 256);
        $newEntry = ['title' => $longTitle, 'body' => 'Valid body', 'tags' => ['weapon']];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/compendium')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('error', $data['status']);
    }

    public function testAddEntryBodyTooLong(): void
    {
        $longBody = str_repeat('B', 10001);
        $newEntry = ['title' => 'Valid', 'body' => $longBody, 'tags' => ['weapon']];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/compendium')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('error', $data['status']);
    }

    public function testAddEntryTooManyTags(): void
    {
        $tags = array_fill(0, 11, 'tag');
        $newEntry = ['title' => 'Valid', 'body' => 'Valid body', 'tags' => $tags];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/compendium')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('error', $data['status']);
    }

    public function testAddEntryTagTooLong(): void
    {
        $tags = ['short', str_repeat('X', 51)];
        $newEntry = ['title' => 'Valid', 'body' => 'Valid body', 'tags' => $tags];
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/compendium')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($newEntry);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('error', $data['status']);
    }

    public function testUpdateEntryPayloadTooLarge(): void
    {
        $entry = $this->service->getBySlug('sword');
        $id = $entry['id'];
        $largeBody = str_repeat('A', 1024 * 1024);  // 1MB
        $updatedData = ['title' => 'Big', 'body' => $largeBody, 'tags' => ['weapon']];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', "/compendium/{$id}")
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($updatedData);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('error', $data['status']);
    }

    public function testUpdateEntryTitleTooLong(): void
    {
        $entry = $this->service->getBySlug('sword');
        $id = $entry['id'];
        $longTitle = str_repeat('T', 256);
        $updatedData = ['title' => $longTitle, 'body' => 'Valid body', 'tags' => ['weapon']];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', "/compendium/{$id}")
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($updatedData);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('error', $data['status']);
    }

    public function testUpdateEntryBodyTooLong(): void
    {
        $entry = $this->service->getBySlug('sword');
        $id = $entry['id'];
        $longBody = str_repeat('B', 10001);
        $updatedData = ['title' => 'Valid', 'body' => $longBody, 'tags' => ['weapon']];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', "/compendium/{$id}")
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($updatedData);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('error', $data['status']);
    }

    public function testUpdateEntryTooManyTags(): void
    {
        $entry = $this->service->getBySlug('sword');
        $id = $entry['id'];
        $tags = array_fill(0, 11, 'tag');
        $updatedData = ['title' => 'Valid', 'body' => 'Valid body', 'tags' => $tags];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', "/compendium/{$id}")
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($updatedData);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('error', $data['status']);
    }

    public function testUpdateEntryTagTooLong(): void
    {
        $entry = $this->service->getBySlug('sword');
        $id = $entry['id'];
        $tags = ['short', str_repeat('X', 51)];
        $updatedData = ['title' => 'Valid', 'body' => 'Valid body', 'tags' => $tags];
        $request = (new ServerRequestFactory())
            ->createServerRequest('PUT', "/compendium/{$id}")
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->testtoken)
            ->withParsedBody($updatedData);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals('error', $data['status']);
    }
}
