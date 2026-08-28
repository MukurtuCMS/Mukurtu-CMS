<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_search\Kernel;

use Drupal\search_api\Item\Field;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests that mukurtu_browse_auto_index stays within MySQL's 64-key limit.
 *
 * `search_api_db` creates one database key per indexed field on the
 * denormalized index table, and MySQL/MariaDB cap a table at 64 keys. Older
 * releases added a `__name__text` and `__uuid` field for every taxonomy
 * reference field on every node bundle, which overflowed the limit on sites
 * with content-type-specific vocabularies and logged "1069 Too many keys
 * specified" during install.
 *
 * The index now carries a fixed field set plus two index-wide aggregate
 * fields (all_taxonomy_term_names / all_taxonomy_term_uuids) populated by the
 * taxonomy_term_aggregates processor, so new content types and fields add no
 * database keys.
 *
 * @see mukurtu_search_rebuild_index()
 * @see \Drupal\mukurtu_search\Plugin\search_api\processor\TaxonomyTermAggregates
 */
#[Group('mukurtu_search')]
class BrowseAutoIndexKeyBudgetTest extends ProtocolAwareEntityTestBase {

  /**
   * The hard MySQL/MariaDB limit on indexes per table.
   */
  private const MAX_KEYS = 64;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api',
    'search_api_db',
    'mukurtu_search',
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
      ],
      'status' => TRUE,
    ])->save();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_search');
    require_once $module_path . '/mukurtu_search.module';

    // Create a taxonomy-reference field storage for every field the shipped
    // index config resolves through, so Index::save() keeps the fields.
    foreach ($this->referencedFieldNames() as $field_name) {
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

    // Install the real shipped index config, pointed at the test server.
    $data = Yaml::parseFile($module_path . '/config/install/search_api.index.mukurtu_browse_auto_index.yml');
    unset($data['dependencies']);
    $data['server'] = 'test_search_server';
    Index::create($data)->save();
  }

  /**
   * Field names the shipped index config resolves taxonomy term names through.
   */
  private function referencedFieldNames(): array {
    $names = array_unique(array_map(
      fn (array $definition) => explode(':', $definition['property_path'])[0],
      mukurtu_search_static_taxonomy_name_fields()
    ));
    // field_communities is a mukurtu_protocol computed reference field and is
    // provided by the test base; the rest are plain taxonomy references.
    return array_values(array_diff($names, ['field_communities']));
  }

  /**
   * Loads the index fresh from storage.
   */
  private function reloadIndex(): Index {
    return \Drupal::entityTypeManager()
      ->getStorage('search_api_index')
      ->loadUnchanged('mukurtu_browse_auto_index');
  }

  /**
   * The shipped config installs cleanly and stays under the key limit.
   */
  public function testShippedConfigIsWithinKeyBudget(): void {
    $index = $this->reloadIndex();
    $this->assertNotNull($index, 'Shipped index config installed.');

    $field_ids = array_keys($index->getFields());

    $this->assertLessThan(
      self::MAX_KEYS,
      count($field_ids),
      'Shipped index defines fewer than 64 fields (one DB key each).'
    );

    $uuid_fields = preg_grep('/__uuid$/', $field_ids);
    $this->assertSame([], array_values($uuid_fields), 'No per-field __uuid variants ship on the DB index.');

    $this->assertNotNull($index->getField('all_taxonomy_term_names'));
    $this->assertNotNull($index->getField('all_taxonomy_term_uuids'));
    $this->assertArrayHasKey('taxonomy_term_aggregates', $index->getProcessors());
  }

  /**
   * Rebuilding after adding content-type-specific taxonomy fields adds no keys.
   */
  public function testRebuildDoesNotAddPerFieldKeysForNewTaxonomyFields(): void {
    $before = count($this->reloadIndex()->getFields());

    // A content-type-specific taxonomy reference field, like Place's
    // field_place_type - not in the static set.
    FieldStorageConfig::create([
      'field_name' => 'field_place_type',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_place_type',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Place type',
    ])->save();

    // Simulate a stale per-field variant left by an older release.
    $index = $this->reloadIndex();
    $stale = new Field($index, 'node__field_place_type__uuid');
    $stale->setType('text');
    $stale->setDatasourceId('entity:node');
    $stale->setPropertyPath('field_place_type:entity:uuid');
    $stale->setLabel('stale');
    $index->addField($stale);
    $index->save();

    mukurtu_search_rebuild_index();

    $index = $this->reloadIndex();
    $field_ids = array_keys($index->getFields());

    $this->assertNotContains('node__field_place_type__uuid', $field_ids, 'Stale __uuid field was stripped.');
    $this->assertNotContains('node__field_place_type__name__text', $field_ids, 'No per-field __name__text was added.');
    $this->assertSame([], array_values(preg_grep('/__uuid$/', $field_ids)), 'No __uuid fields remain.');
    $this->assertLessThan(self::MAX_KEYS, count($field_ids));
    $this->assertLessThanOrEqual($before, count($field_ids), 'Rebuild did not grow the field set.');

    // The static facet/search variants survive the rebuild.
    foreach (array_keys(mukurtu_search_static_taxonomy_name_fields()) as $field_id) {
      $this->assertNotNull($index->getField($field_id), "Rebuild dropped $field_id.");
      $this->assertNotNull($index->getField($field_id . '__text'), "Rebuild dropped {$field_id}__text.");
    }
    $this->assertNotNull($index->getField('all_taxonomy_term_names'));
    $this->assertNotNull($index->getField('all_taxonomy_term_uuids'));
  }

  /**
   * Update hook 40006 strips stale per-field variants and reindexes.
   */
  public function testUpdate40006ConvergesExistingSite(): void {
    require_once \Drupal::service('extension.list.module')->getPath('mukurtu_search') . '/mukurtu_search.install';

    FieldStorageConfig::create([
      'field_name' => 'field_place_type',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_place_type',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Place type',
    ])->save();

    // Seed the pre-fix state: per-field variants on the index and referenced
    // by a text processor's field list.
    $index = $this->reloadIndex();
    foreach ([
      'node__field_place_type__uuid' => ['text', 'field_place_type:entity:uuid'],
      'node__field_place_type__name__text' => ['text', 'field_place_type:entity:name'],
    ] as $id => [$type, $path]) {
      $field = new Field($index, $id);
      $field->setType($type);
      $field->setDatasourceId('entity:node');
      $field->setPropertyPath($path);
      $field->setLabel($id);
      $index->addField($field);
    }
    $processors = $index->getProcessors();
    $tokenizer = $processors['tokenizer']->getConfiguration();
    $tokenizer['fields'][] = 'node__field_place_type__name__text';
    $processors['tokenizer']->setConfiguration($tokenizer);
    $index->setProcessors($processors);
    $index->save();

    mukurtu_search_update_40006();

    $index = $this->reloadIndex();
    $field_ids = array_keys($index->getFields());
    $this->assertNotContains('node__field_place_type__uuid', $field_ids);
    $this->assertNotContains('node__field_place_type__name__text', $field_ids);
    $this->assertLessThan(self::MAX_KEYS, count($field_ids));

    $tokenizer_fields = $index->getProcessor('tokenizer')->getConfiguration()['fields'];
    $this->assertNotContains('node__field_place_type__name__text', $tokenizer_fields, 'Stale field scrubbed from processor list.');
    $this->assertContains('all_taxonomy_term_names', $tokenizer_fields, 'Aggregate field added to processor list.');

    // Idempotent.
    mukurtu_search_update_40006();
    $this->assertLessThan(self::MAX_KEYS, count($this->reloadIndex()->getFields()));
  }

}
