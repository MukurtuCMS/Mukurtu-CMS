<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_search\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that facet-backing string taxonomy name fields survive indexing.
 *
 * mukurtu_search_rebuild_index() used to remove every field on
 * mukurtu_browse_auto_index and only restore the fulltext "__name__text"
 * variant of each taxonomy reference field via the dynamic
 * FieldAvailableForIndexing pass (see
 * BaseFieldsSearchIndexSubscriber::defaultFieldIndex()). It never restored
 * the plain string "__name" variant that facets (Category, Format,
 * Language, etc.) use as their field_identifier, so every facet built on
 * one of those 16 fields broke with "No available query types were found"
 * once mukurtu_search_update_40001() ran on an already-installed site.
 * mukurtu_search_update_40005() repairs already-affected sites, and
 * mukurtu_search_rebuild_index() itself now re-adds those 16 fields so the
 * problem cannot recur on a future rebuild.
 *
 * mukurtu_search itself is not enabled here: its declared dependency chain
 * (mukurtu_collection, paragraphs, media, search_api_glossary, token)
 * isn't needed to exercise mukurtu_search_rebuild_index() and
 * mukurtu_search_update_40005() directly. Both files are required
 * directly instead, mirroring BrowseCollapseIndexRestoreTest.
 *
 * @see mukurtu_search_rebuild_index()
 * @see mukurtu_search_update_40005()
 * @see mukurtu_search_static_taxonomy_name_fields()
 */
#[Group('mukurtu_search')]
class StaticTaxonomyNameFieldRestoreTest extends ProtocolAwareEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
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

    Index::create([
      'id' => 'mukurtu_browse_auto_index',
      'name' => 'Mukurtu Browse Auto Content Index',
      'status' => TRUE,
      'server' => 'test_search_server',
      'datasource_settings' => [
        'entity:node' => [],
      ],
      'tracker_settings' => [
        'default' => [],
      ],
      'options' => [
        'cron_limit' => -1,
        'index_directly' => FALSE,
      ],
    ])->save();

    // The property paths (e.g. "field_category:entity:name") only resolve
    // to a data definition, and so only survive Index::save(), if the
    // underlying taxonomy-reference field storage actually exists. Create
    // one per static field so the save-time resolution this test exercises
    // matches a real site, where these are all real content fields.
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_search');
    require_once $module_path . '/mukurtu_search.module';
    require_once $module_path . '/mukurtu_search.install';

    $field_names = array_unique(array_map(
      fn (array $definition) => explode(':', $definition['property_path'])[0],
      mukurtu_search_static_taxonomy_name_fields()
    ));
    foreach ($field_names as $field_name) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'settings' => ['target_type' => 'taxonomy_term'],
      ])->save();
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'bundle' => 'protocol_aware_content',
        'label' => $field_name,
      ])->save();
    }
  }

  /**
   * Tests that rebuilding the index does not drop the static string fields.
   */
  public function testRebuildIndexPreservesStaticStringFields(): void {
    mukurtu_search_rebuild_index();

    /** @var \Drupal\search_api\IndexInterface $index */
    $index = \Drupal::entityTypeManager()
      ->getStorage('search_api_index')
      ->loadUnchanged('mukurtu_browse_auto_index');

    foreach (mukurtu_search_static_taxonomy_name_fields() as $field_id => $definition) {
      $field = $index->getField($field_id);
      $this->assertNotNull($field, "Rebuild dropped $field_id.");
      $this->assertSame('string', $field->getType());
      $this->assertSame('entity:node', $field->getDatasourceId());
      $this->assertSame($definition['property_path'], $field->getPropertyPath());
    }
  }

  /**
   * Tests that the update hook restores fields missing after the rebuild bug.
   */
  public function testUpdate40005RestoresMissingStaticFields(): void {
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = Index::load('mukurtu_browse_auto_index');
    foreach (mukurtu_search_static_taxonomy_name_fields() as $field_id => $definition) {
      $this->assertNull($index->getField($field_id));
    }

    mukurtu_search_update_40005();

    /** @var \Drupal\search_api\IndexInterface $index */
    $index = \Drupal::entityTypeManager()
      ->getStorage('search_api_index')
      ->loadUnchanged('mukurtu_browse_auto_index');

    foreach (mukurtu_search_static_taxonomy_name_fields() as $field_id => $definition) {
      $field = $index->getField($field_id);
      $this->assertNotNull($field, "Update hook did not restore $field_id.");
      $this->assertSame('string', $field->getType());
      $this->assertSame('entity:node', $field->getDatasourceId());
      $this->assertSame($definition['property_path'], $field->getPropertyPath());
    }
  }

  /**
   * Tests that running the update hook twice does not error or duplicate.
   */
  public function testUpdate40005IsIdempotent(): void {
    mukurtu_search_update_40005();
    mukurtu_search_update_40005();

    /** @var \Drupal\search_api\IndexInterface $index */
    $index = \Drupal::entityTypeManager()
      ->getStorage('search_api_index')
      ->loadUnchanged('mukurtu_browse_auto_index');

    foreach (mukurtu_search_static_taxonomy_name_fields() as $field_id => $definition) {
      $this->assertNotNull($index->getField($field_id));
    }
  }

}
