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
 * Tests that the API key fields ask for a width the page can afford.
 *
 * An input with a size attribute is as wide as that many of the font's
 * average characters, so its width scales with the reader's text size. Left
 * at Drupal's default of 60, these two fields rendered 1395px wide at 200%
 * text size and pushed the Local Contexts projects pages 205px past a 1280px
 * viewport, which is a horizontal scroll a reader cannot avoid (#2392, WCAG
 * 1.4.4 Resize Text).
 *
 * The width that results depends on the font the browser picks, so this
 * pins the request rather than the pixels: anything much above 30 reopens
 * the defect on whichever platform has the widest default font.
 *
 * @see \Drupal\mukurtu_local_contexts\Form\ManageSupportedProjectsBase::buildForm()
 */
#[Group('mukurtu_local_contexts')]
class ApiKeyFieldWidthTest extends KernelTestBase {

  /**
   * The largest #size these fields may ask for.
   *
   * At 200% text size a 1280px viewport has room for roughly 35 characters
   * once the admin theme's chrome is accounted for.
   */
  private const MAX_SIZE = 30;

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
    $mock = new MockHandler([new Response(200, [], json_encode(['results' => []]))]);
    $this->container->set('http_client', new Client(['handler' => HandlerStack::create($mock)]));
  }

  /**
   * Tests that both API key fields cap the width they ask for.
   */
  public function testApiKeyFieldsCapTheirRequestedWidth(): void {
    $form = \Drupal::formBuilder()->getForm(ManageSupportedProjectsSite::class);

    $fields = [
      'api_key' => $form['api_key_wrapper']['add']['api_key'] ?? NULL,
      'label' => $form['api_key_wrapper']['add']['label'] ?? NULL,
    ];

    foreach ($fields as $name => $element) {
      $this->assertNotNull($element, "The $name field is still on the form.");
      $this->assertSame('textfield', $element['#type']);
      $this->assertArrayHasKey('#size', $element,
        "The $name field sets #size; without it Drupal defaults to 60, which "
        . 'overflows the page at 200% text size.');
      $this->assertLessThanOrEqual(self::MAX_SIZE, $element['#size'],
        "The $name field asks for a width that overflows at 200% text size.");
    }
  }

}
