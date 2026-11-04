<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_local_contexts\LocalContextsApi;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests what editors and the log see when a Hub request fails.
 *
 * A proxy in front of the Hub can answer with an HTML page instead of the
 * Hub's JSON. That used to show as "Unknown error." with nothing logged.
 */
#[Group('mukurtu_local_contexts')]
class HubErrorMessageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dblog',
    'field',
    'geofield',
    'leaflet',
    'node',
    'media',
    'taxonomy',
    'content_moderation',
    'workflows',
    'options',
    'path_alias',
    'system',
    'text',
    'user',
    'og',
    'mukurtu_core',
    'mukurtu_protocol',
    'mukurtu_local_contexts',
  ];

  /**
   * An API key that must never reach the log.
   */
  protected const KEY = 'secret-api-key-123';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['mukurtu_local_contexts']);
  }

  /**
   * Sends every Hub request to the given responses.
   */
  protected function mockHub(array $responses): void {
    $client = new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);
    $this->container->set('http_client', $client);
  }

  /**
   * Returns the module's log entries as rendered strings.
   *
   * Fails if any entry contains the API key.
   */
  protected function log(): array {
    $rows = $this->container->get('database')->select('watchdog', 'w')
      ->fields('w', ['message', 'variables'])
      ->condition('type', 'mukurtu_local_contexts')
      ->execute()
      ->fetchAll();
    $entries = array_map(fn($row) => strtr($row->message, unserialize($row->variables, ['allowed_classes' => FALSE]) ?: []), $rows);
    foreach ($entries as $entry) {
      $this->assertStringNotContainsString(self::KEY, $entry);
    }
    return $entries;
  }

  /**
   * The Hub's own error is shown, and the failure is logged.
   */
  public function testHubError(): void {
    $this->mockHub([new Response(401, ['Content-Type' => 'application/json'], json_encode(['detail' => 'Invalid token.']))]);
    $api = new LocalContextsApi();

    $this->assertFalse($api->validateApiKey(self::KEY));
    $this->assertSame('Invalid token.', $api->getErrorMessage());
    $this->assertSame(['Local Contexts Hub request to /projects failed: HTTP 401, application/json.'], $this->log());
  }

  /**
   * A page that isn't the Hub's JSON gets a plain message with the status.
   */
  public function testNonJsonResponse(): void {
    $this->mockHub([new Response(403, ['Content-Type' => 'text/html; charset=UTF-8'], '<!DOCTYPE html><title>Just a moment...</title>')]);
    $api = new LocalContextsApi();

    $this->assertFalse($api->validateApiKey(self::KEY));
    $this->assertSame("The Local Contexts Hub didn't accept the request (HTTP 403). Try again later. If this keeps happening, ask your site administrator to check the site log.", $api->getErrorMessage());
    $this->assertSame(403, $api->getStatusCode());
    $this->assertSame(['Local Contexts Hub request to /projects failed: HTTP 403, text/html; charset=UTF-8.'], $this->log());
  }

  /**
   * A connection failure gets a plain message; the details are logged.
   */
  public function testConnectionFailure(): void {
    $this->mockHub([new ConnectException('cURL error 6: Could not resolve host', new Request('GET', 'https://localcontextshub.org/api/v2/projects'))]);
    $api = new LocalContextsApi();

    $this->assertSame([], $api->makeRequest('projects', self::KEY));
    $this->assertSame("Couldn't connect to the Local Contexts Hub. Try again later. If this keeps happening, ask your site administrator to check the site log.", $api->getErrorMessage());
    $this->assertSame(0, $api->getStatusCode());
    $this->assertSame(["Couldn't connect to the Local Contexts Hub for /projects: cURL error 6: Could not resolve host"], $this->log());
  }

  /**
   * A successful request returns the Hub's data and logs nothing.
   */
  public function testSuccess(): void {
    $this->mockHub([new Response(200, ['Content-Type' => 'application/json'], json_encode(['results' => [['unique_id' => 'p1']]]))]);
    $api = new LocalContextsApi();

    $this->assertSame(['results' => [['unique_id' => 'p1']]], $api->makeRequest('/projects', self::KEY));
    $this->assertSame('', $api->getErrorMessage());
    $this->assertSame([], $this->log());
  }

}
