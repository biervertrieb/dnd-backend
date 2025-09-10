<?php

use App\Services\JournalService;
use App\Util\JWT;
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
        $this->assertEquals('Session 3', $data['title']);
        $this->assertEquals('We met a dragon!', $data['body']);
        $this->assertEquals(3, $data['day']);

        // Verify it was added
        $entries = $this->service->getEntries();
        $this->assertCount(3, $entries);
    }

    public function testAddEntryRouteValidationError()
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
}
