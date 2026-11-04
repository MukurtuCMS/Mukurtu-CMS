<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_local_contexts\Form\ManageSupportedProjectsSite;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the message when a saved key's project list can't be fetched.
 */
#[Group('mukurtu_local_contexts')]
class ProjectListErrorMessageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['og', 'mukurtu_local_contexts']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('workflow');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('mukurtu_local_contexts', [
      'mukurtu_local_contexts_supported_projects',
      'mukurtu_local_contexts_projects',
      'mukurtu_local_contexts_labels',
      'mukurtu_local_contexts_notices',
      'mukurtu_local_contexts_notice_translations',
      'mukurtu_local_contexts_api_key_labels',
      'mukurtu_local_contexts_purge_log',
    ]);

    // The form asks the Hub for each key's projects while building. There are
    // no keys configured here, but mock the client anyway so a dropped stub
    // cannot turn into a real request from a test run.
    $this->config('mukurtu_local_contexts.settings')
      ->set('site_api_keys', ['secret-api-key-123'])
      ->save();
  }

  /**
   * The error reads as a sentence; only the masked key is code.
   */
  public function testHubErrorInMessage(): void {
    $mock = new MockHandler([new Response(403, ['Content-Type' => 'text/html'], '<title>Just a moment...</title>')]);
    $this->container->set('http_client', new Client(['handler' => HandlerStack::create($mock)]));

    \Drupal::formBuilder()->getForm(ManageSupportedProjectsSite::class);

    $errors = array_map('strval', $this->container->get('messenger')->messagesByType('error'));
    // The error is escaped like any placeholder; browsers show "didn't".
    $this->assertSame(["Couldn't get the Local Contexts projects for the API key <code>secret-apiXXXXXXXX</code>: The Local Contexts Hub didn&#039;t accept the request (HTTP 403). Try again later. If this keeps happening, ask your site administrator to check the site log."], $errors);
  }

}
