<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multilingual\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_multilingual_update_40009().
 *
 * Wires the already-enabled LanguageWithFallback Search API processor to an
 * indexed field on the profile's Search API indexes, so views/facets can
 * filter on it (docs/content-language-policy.md).
 *
 * @see mukurtu_multilingual_update_40009()
 */
#[Group('mukurtu_multilingual')]
class SearchApiLanguageFallbackFieldTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'search_api',
    'search_api_db',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig('search_api');

    Server::create([
      'id' => 'test_search_server',
      'name' => 'Test search server',
      'backend' => 'search_api_db',
      'backend_config' => [
        'database' => 'default:default',
        'min_chars' => 3,
        'matching' => 'words',
        'phrase' => 'bigram',
      ],
      'status' => TRUE,
    ])->save();

    // Only one of the 6 real index IDs is created here - the update hook's
    // loop logic is identical for each, so one representative index plus a
    // deliberately-missing one (to exercise the "module not installed"
    // skip path) is sufficient coverage without duplicating 6x setup.
    Index::create([
      'id' => 'mukurtu_default_content_index',
      'name' => 'Mukurtu default content index',
      'status' => TRUE,
      'server' => 'test_search_server',
      'datasource_settings' => [
        'entity:node' => [],
      ],
      'tracker_settings' => [
        'default' => [],
      ],
      'processor_settings' => [
        'language_with_fallback' => [],
      ],
      'options' => [
        'cron_limit' => -1,
        'index_directly' => FALSE,
      ],
    ])->save();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_multilingual');
    require_once $module_path . '/mukurtu_multilingual.install';
  }

  /**
   * Tests that the update hook adds the fallback field to an existing index.
   */
  public function testAddsFallbackFieldToExistingIndex(): void {
    $index = Index::load('mukurtu_default_content_index');
    $this->assertNull($index->getField('language_with_fallback'));

    mukurtu_multilingual_update_40009();

    /** @var \Drupal\search_api\IndexInterface $index */
    $index = \Drupal::entityTypeManager()
      ->getStorage('search_api_index')
      ->loadUnchanged('mukurtu_default_content_index');

    $field = $index->getField('language_with_fallback');
    $this->assertNotNull($field, 'Update hook did not add the language_with_fallback field.');
    $this->assertSame('string', $field->getType());
    $this->assertSame('language_with_fallback', $field->getPropertyPath());
    $this->assertNull($field->getDatasourceId(), 'The field should be index-wide (no datasource), matching the processor-derived property.');
  }

  /**
   * Tests that a site missing one of the 6 target indexes (e.g. because the
   * owning module, like mukurtu_solr, isn't installed) doesn't error.
   */
  public function testSkipsMissingIndexesWithoutError(): void {
    $this->assertNull(Index::load('mukurtu_dictionary_solr_index'));

    mukurtu_multilingual_update_40009();

    $this->assertNull(Index::load('mukurtu_dictionary_solr_index'));
  }

  /**
   * Tests that running the update hook twice does not error or duplicate.
   */
  public function testIsIdempotent(): void {
    mukurtu_multilingual_update_40009();
    mukurtu_multilingual_update_40009();

    /** @var \Drupal\search_api\IndexInterface $index */
    $index = \Drupal::entityTypeManager()
      ->getStorage('search_api_index')
      ->loadUnchanged('mukurtu_default_content_index');

    $this->assertNotNull($index->getField('language_with_fallback'));
  }

}
