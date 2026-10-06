<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_search\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\entity_test\Entity\EntityTestMulRevChanged;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_search\Event\FieldAvailableForIndexing;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that search matches words with apostrophe-like marks inside them.
 *
 * Transliteration turns marks such as the ʻokina into an apostrophe,
 * backtick, or question mark, which the tokenizer treats as a word break, so
 * "hiʻilei" was indexed as "hi" and "ilei" and a search for "hiilei" found
 * nothing. The ignore_character processor strips them before tokenizing.
 *
 * mukurtu_search itself is not enabled here: its dependency chain is not
 * needed to exercise the helper and the event, so the module file is
 * required directly instead.
 *
 * @see mukurtu_search_enable_ignore_character_processor()
 */
#[Group('mukurtu_search')]
class IgnoreCharacterProcessorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'field',
    'search_api',
    'search_api_db',
    'system',
    'text',
    'user',
  ];

  /**
   * The index under test.
   */
  protected Index $index;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('user');
    $this->installConfig(['search_api']);

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_search');
    require_once $module_path . '/mukurtu_search.module';

    // Same backend settings as search_api.server.mukurtu_database_server.
    Server::create([
      'id' => 'test_server',
      'name' => 'Test server',
      'backend' => 'search_api_db',
      'backend_config' => [
        'database' => 'default:default',
        'min_chars' => 3,
        'matching' => 'partial',
      ],
      'status' => TRUE,
    ])->save();

    // Same text processors as the shipped indexes, minus ignore_character.
    $this->index = Index::create([
      'id' => 'test_index',
      'name' => 'Test index',
      'status' => TRUE,
      'server' => 'test_server',
      'datasource_settings' => [
        'entity:entity_test_mulrev_changed' => [],
      ],
      'tracker_settings' => [
        'default' => [],
      ],
      'field_settings' => [
        'name' => [
          'label' => 'Name',
          'datasource_id' => 'entity:entity_test_mulrev_changed',
          'property_path' => 'name',
          'type' => 'text',
        ],
        'type' => [
          'label' => 'Type',
          'datasource_id' => 'entity:entity_test_mulrev_changed',
          'property_path' => 'type',
          'type' => 'string',
        ],
      ],
      'processor_settings' => [
        'ignorecase' => [
          'all_fields' => TRUE,
          'weights' => ['preprocess_index' => -20, 'preprocess_query' => -20],
        ],
        'tokenizer' => [
          'all_fields' => TRUE,
          'weights' => ['preprocess_index' => -6, 'preprocess_query' => -6],
        ],
        'transliteration' => [
          'all_fields' => TRUE,
          'weights' => ['preprocess_index' => -20, 'preprocess_query' => -20],
        ],
      ],
      'options' => [
        'cron_limit' => -1,
        'index_directly' => FALSE,
      ],
    ]);
    $this->index->save();

    EntityTestMulRevChanged::create([
      'name' => 'Hiʻilei ʻaʻaliʻi Hawaiʼi Tłʼízí Qurʾān baꞌaa',
      'type' => 'entity_test_mulrev_changed',
    ])->save();
    $this->index->indexItems();
  }

  /**
   * Tests that the helper makes searches ignore marks inside words.
   */
  public function testHelperFixesOkinaSearch(): void {
    // Without the processor, the mark splits the word in two.
    $this->assertSame(0, $this->search('hiilei'));
    $this->assertSame(1, $this->search('ilei'));

    $this->assertTrue(mukurtu_search_enable_ignore_character_processor('test_index'));
    $this->assertFalse(mukurtu_search_enable_ignore_character_processor('test_index'));
    $this->assertFalse(mukurtu_search_enable_ignore_character_processor('no_such_index'));

    $index = Index::load('test_index');
    $processor = $index->getProcessor('ignore_character');
    $this->assertSame(['name'], $processor->getConfiguration()['fields']);
    $this->assertSame(1, $index->getTrackerInstance()->getRemainingItemsCount(), 'Adding the processor queued the item for reindexing.');

    $index->indexItems();
    foreach (['hiilei', 'hiʻilei', "hi'ilei", 'hi’ilei', 'aalii', 'hawaii', 'Hawaiʼi', 'tlizi', 'quran', 'baaa'] as $keys) {
      $this->assertSame(1, $this->search($keys), "Search for '$keys' matches.");
    }
  }

  /**
   * Tests that a new text field gets the processor, and a string field not.
   */
  public function testNewTextFieldIsAddedToProcessor(): void {
    mukurtu_search_enable_ignore_character_processor('test_index');

    $event = new FieldAvailableForIndexing('entity_test_mulrev_changed', 'entity_test_mulrev_changed', NULL);
    $event->indexField('test_index', 'name_copy', 'name', 'Name copy', 'text');
    $event->indexField('test_index', 'name_string', 'name', 'Name string', 'string');

    $fields = Index::load('test_index')->getProcessor('ignore_character')->getConfiguration()['fields'];
    $this->assertContains('name_copy', $fields);
    $this->assertNotContains('name_string', $fields);
  }

  /**
   * Tests that each shipped index strips the marks from every text field.
   */
  #[DataProvider('providerShippedIndexes')]
  public function testShippedIndexConfig(string $module, string $index_id): void {
    $modules_dir = dirname(__DIR__, 4);
    $config = (new FileStorage("$modules_dir/$module/config/install"))
      ->read("search_api.index.$index_id");
    $this->assertIsArray($config, "search_api.index.$index_id exists.");
    $processors = $config['processor_settings'];
    $this->assertArrayHasKey('ignore_character', $processors);
    $ignore = $processors['ignore_character'];

    $text_fields = array_keys(array_filter(
      $config['field_settings'],
      fn (array $field) => $field['type'] === 'text',
    ));
    $this->assertNotEmpty($text_fields);
    $this->assertFalse($ignore['all_fields'], 'String fields such as URLs are left alone.');
    $this->assertEqualsCanonicalizing($text_fields, $ignore['fields']);
    $this->assertSame(['Pf', 'Pi', 'Sk'], $ignore['ignorable_classes']);
    $this->assertStringContainsString("'", $ignore['ignorable']);

    // Transliteration must turn each mark into ASCII first, and the tokenizer
    // must not see it.
    foreach (['preprocess_index', 'preprocess_query'] as $stage) {
      $weight = $ignore['weights'][$stage];
      $this->assertGreaterThan($processors['transliteration']['weights'][$stage], $weight, "$stage runs after transliteration.");
      $this->assertLessThan($processors['tokenizer']['weights'][$stage], $weight, "$stage runs before the tokenizer.");
    }
  }

  /**
   * Data provider for testShippedIndexConfig().
   */
  public static function providerShippedIndexes(): array {
    return [
      'browse auto' => ['mukurtu_search', 'mukurtu_browse_auto_index'],
      'collection' => ['mukurtu_collection', 'mukurtu_collection_index'],
      'default content' => ['mukurtu_browse', 'mukurtu_default_content_index'],
      'dictionary' => ['mukurtu_dictionary', 'mukurtu_dictionary_index'],
    ];
  }

  /**
   * Returns the number of results for a fulltext search.
   */
  protected function search(string $keys): int {
    // The database backend returns the count as a string.
    return (int) Index::load('test_index')->query()
      ->keys($keys)
      ->execute()
      ->getResultCount();
  }

}
