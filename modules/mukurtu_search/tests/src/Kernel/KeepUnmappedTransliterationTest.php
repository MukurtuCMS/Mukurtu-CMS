<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_search\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\entity_test\Entity\EntityTestMulRevChanged;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_search\EventSubscriber\GatheringProcessorsSubscriber;
use Drupal\mukurtu_search\Plugin\search_api\processor\KeepUnmappedTransliteration;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Plugin\search_api\processor\Transliteration;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\AbstractLogger;

/**
 * Tests that words in scripts with no transliteration can be searched.
 *
 * Drupal's transliteration turns characters it has no mapping for into "?",
 * which the tokenizer drops, so words in Osage, Tifinagh, Adlam, N'Ko, and
 * Cherokee were never indexed.
 *
 * mukurtu_search itself is not enabled here: its dependency chain is not
 * needed, so the event subscriber is registered directly and the install
 * file is required directly.
 *
 * @see \Drupal\mukurtu_search\Plugin\search_api\processor\KeepUnmappedTransliteration
 */
#[Group('mukurtu_search')]
class KeepUnmappedTransliterationTest extends KernelTestBase {

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
   * Words to index, keyed by a label.
   */
  protected const WORDS = [
    'osage' => '𐓏𐓘𐓻𐓘𐓻𐓟',
    'tifinagh' => 'ⵜⴰⵎⴰⵣⵉⵖⵜ',
    'adlam' => '𞤀𞤣𞤤𞤢𞤥',
    'nko' => 'ߒߞߏ',
    'cherokee' => 'ᏣᎳᎩ',
    'hawaiian' => 'ʻŌlelo',
  ];

  /**
   * Entity IDs of the indexed words, keyed like ::WORDS.
   *
   * @var int[]
   */
  protected array $ids = [];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->register('mukurtu_search.gathering_processors_event_subscriber', GatheringProcessorsSubscriber::class)
      ->addTag('event_subscriber');
  }

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

    $this->createIndex('test_index', TRUE);
    foreach (static::WORDS as $key => $word) {
      $entity = EntityTestMulRevChanged::create([
        'name' => $word,
        'type' => 'entity_test_mulrev_changed',
      ]);
      $entity->save();
      $this->ids[$key] = (int) $entity->id();
    }
    $this->assertSame(count(static::WORDS), Index::load('test_index')->indexItems());
  }

  /**
   * Tests that the transliteration processor is Mukurtu's version.
   */
  public function testProcessorClassIsReplaced(): void {
    $processor = Index::load('test_index')->getProcessor('transliteration');
    $this->assertInstanceOf(KeepUnmappedTransliteration::class, $processor);

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_search');
    $services = Yaml::decode(file_get_contents("$module_path/mukurtu_search.services.yml"));
    $this->assertSame(GatheringProcessorsSubscriber::class, $services['services']['mukurtu_search.gathering_processors_event_subscriber']['class']);
  }

  /**
   * Tests that each word is found by a search in its own script.
   */
  public function testWordsAreFoundInTheirOwnScript(): void {
    // Core's processor loses these words entirely.
    $core = Transliteration::create($this->container, [], 'transliteration', []);
    $this->assertSame('??????', $core->getTransliterator()->transliterate(static::WORDS['osage'], 'en'));

    foreach (static::WORDS as $key => $word) {
      $this->assertSame([$this->ids[$key]], $this->search($word), "Search for the $key word finds it.");
    }

    // Cherokee also matches its Latin transliteration and lowercase letters.
    $this->assertSame([$this->ids['cherokee']], $this->search('tsalagi'));
    $this->assertSame([$this->ids['cherokee']], $this->search(mb_strtolower(static::WORDS['cherokee'])));
    $this->assertSame([$this->ids['hawaiian']], $this->search('olelo'));

    // Lowercase Osage matches, and a different Osage word does not.
    $this->assertSame([$this->ids['osage']], $this->search(mb_strtolower(static::WORDS['osage'])));
    $this->assertSame([], $this->search('𐓏𐓘𐓻𐓘𐓻𐓘'));
  }

  /**
   * Tests that filter conditions on a fulltext field keep unmapped words.
   */
  public function testFulltextConditionsKeepUnmapped(): void {
    foreach (['osage', 'tifinagh'] as $key) {
      $ids = [];
      $query = Index::load('test_index')->query()->addCondition('name', static::WORDS[$key]);
      foreach ($query->execute() as $item) {
        $ids[] = (int) $item->getOriginalObject()->getValue()->id();
      }
      $this->assertSame([$this->ids[$key]], $ids, "A condition on the $key word finds it.");
    }
  }

  /**
   * Tests that string fields keep core's transliteration.
   *
   * The database backend stores string fields in utf8mb3 columns, which
   * reject 4-byte characters. setUp() indexing the Osage and Adlam words
   * through the "name_string" field without an exception covers the indexing
   * side; this covers the stored value.
   */
  public function testStringFieldsKeepCoreBehavior(): void {
    $index = Index::load('test_index');
    $item = $index->loadItemsMultiple(['entity:entity_test_mulrev_changed/' . $this->ids['osage'] . ':en']);
    $item = \Drupal::getContainer()->get('search_api.fields_helper')->createItemFromObject($index, reset($item), 'entity:entity_test_mulrev_changed/' . $this->ids['osage'] . ':en');
    $index->getProcessor('transliteration')->preprocessIndexItems([$item]);

    $this->assertSame(['??????'], $item->getField('name_string')->getValues());
    $text = $item->getField('name')->getValues()[0]->getText();
    $this->assertSame(static::WORDS['osage'], $text);
  }

  /**
   * Tests that the update hook reindexes only transliterating indexes.
   */
  public function testUpdateHookReindexes(): void {
    $this->createIndex('plain_index', FALSE);
    $plain = Index::load('plain_index');
    $plain->indexItems();
    $this->assertSame(0, $plain->getTrackerInstance()->getRemainingItemsCount());
    $this->assertSame(0, Index::load('test_index')->getTrackerInstance()->getRemainingItemsCount());

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_search');
    require_once $module_path . '/mukurtu_search.install';
    mukurtu_search_update_40010();

    $this->assertSame(count(static::WORDS), Index::load('test_index')->getTrackerInstance()->getRemainingItemsCount());
    $this->assertSame(0, Index::load('plain_index')->getTrackerInstance()->getRemainingItemsCount());
  }

  /**
   * Tests that the update hook converts existing tables to utf8mb4.
   */
  public function testUpdateHookConvertsTables(): void {
    $database = \Drupal::database();
    if ($database->databaseType() !== 'mysql') {
      $this->markTestSkipped('Only MySQL and MariaDB tables use utf8mb3.');
    }
    $db_info = \Drupal::keyValue('search_api_db.indexes')->get('test_index');
    $text_table = $db_info['field_tables']['name']['table'];
    $string_table = $db_info['field_tables']['name_string']['table'];
    $index_table = $db_info['index_table'];

    // Put the tables back the way unpatched search_api_db created them. They
    // must be empty first, since utf8mb3 cannot hold the Osage text.
    Index::load('test_index')->clear();
    foreach ([$index_table, $string_table] as $table) {
      $database->query("ALTER TABLE {{$table}} CONVERT TO CHARACTER SET 'utf8' COLLATE 'utf8_general_ci'");
    }
    $database->query("ALTER TABLE {{$text_table}} MODIFY [item_id] VARCHAR(150) CHARACTER SET 'utf8' COLLATE 'utf8_general_ci'");

    // With utf8mb3 tables, the Osage item drops out of the index.
    $index = Index::load('test_index');
    $this->assertSame(count(static::WORDS) - 2, $index->indexItems());
    $this->assertSame([], $this->search(static::WORDS['osage']));

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_search');
    require_once $module_path . '/mukurtu_search.install';
    $sandbox = [];
    do {
      mukurtu_search_update_40009($sandbox);
    } while ($sandbox['#finished'] < 1);

    $this->assertSame('utf8mb4_general_ci', $this->tableCollation($index_table));
    $this->assertSame('utf8mb4_general_ci', $this->tableCollation($string_table));
    $this->assertSame('utf8mb4_bin', $this->tableCollation($text_table));
    $this->assertSame('utf8mb4_general_ci', $this->columnCollation($text_table, 'item_id'));

    $index = Index::load('test_index');
    $index->reindex();
    $this->assertSame(count(static::WORDS), $index->indexItems());
    $this->assertSame([$this->ids['osage']], $this->search(static::WORDS['osage']));
  }

  /**
   * Tests that a table MySQL can't convert is logged and skipped.
   */
  public function testUpdateHookSkipsTableItCannotConvert(): void {
    $database = \Drupal::database();
    if ($database->databaseType() !== 'mysql') {
      $this->markTestSkipped('Only MySQL and MariaDB tables use utf8mb3.');
    }

    // An old row format limits keys to 767 bytes: 255 characters fit in
    // utf8mb3 (765 bytes) but not in utf8mb4 (1020 bytes).
    $legacy_table = 'search_api_db_test_index_legacy';
    $database->query("CREATE TABLE {{$legacy_table}} ([item_id] VARCHAR(150) NOT NULL, [value] VARCHAR(255) NOT NULL, PRIMARY KEY ([value])) ROW_FORMAT=COMPACT CHARACTER SET 'utf8' COLLATE 'utf8_general_ci'");
    $key_value = \Drupal::keyValue('search_api_db.indexes');
    $db_info = $key_value->get('test_index');
    $db_info['field_tables']['legacy'] = [
      'table' => $legacy_table,
      'column' => 'value',
      'type' => 'string',
      'boost' => 1.0,
    ];
    $key_value->set('test_index', $db_info);

    $logger = new class() extends AbstractLogger {

      /**
       * The logged messages, with placeholders replaced.
       *
       * @var string[]
       */
      public array $messages = [];

      /**
       * {@inheritdoc}
       */
      public function log($level, string|\Stringable $message, array $context = []): void {
        $this->messages[] = strtr((string) $message, array_filter($context, 'is_scalar'));
      }

    };
    $this->container->get('logger.factory')->addLogger($logger);

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_search');
    require_once $module_path . '/mukurtu_search.install';
    $sandbox = [];
    do {
      mukurtu_search_update_40009($sandbox);
    } while ($sandbox['#finished'] < 1);

    $this->assertStringStartsWith('utf8mb3_', $this->tableCollation($legacy_table));
    $this->assertSame('utf8mb4_general_ci', $this->tableCollation($db_info['index_table']));
    $this->assertCount(1, $logger->messages);
    $this->assertStringContainsString("Could not convert search table $legacy_table to utf8mb4", $logger->messages[0]);
  }

  /**
   * Returns a table's collation.
   */
  protected function tableCollation(string $table): string {
    return (string) \Drupal::database()->query('SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', [
      ':table' => \Drupal::database()->getPrefix() . $table,
    ])->fetchField();
  }

  /**
   * Returns a column's collation.
   */
  protected function columnCollation(string $table, string $column): string {
    return (string) \Drupal::database()->query('SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column', [
      ':table' => \Drupal::database()->getPrefix() . $table,
      ':column' => $column,
    ])->fetchField();
  }

  /**
   * Creates an index with the same text processors as the shipped indexes.
   *
   * @param string $id
   *   The index ID.
   * @param bool $transliterate
   *   Whether to enable the transliteration processor and add a string field.
   */
  protected function createIndex(string $id, bool $transliterate): void {
    $processors = [
      'ignore_character' => [
        'all_fields' => FALSE,
        'fields' => ['name'],
        'ignorable' => "['\"¿¡!?,.:;]",
        'ignorable_classes' => ['Pf', 'Pi', 'Sk'],
        'weights' => ['preprocess_index' => -10, 'preprocess_query' => -10],
      ],
      'ignorecase' => [
        'all_fields' => TRUE,
        'weights' => ['preprocess_index' => -20, 'preprocess_query' => -20],
      ],
      'tokenizer' => [
        'all_fields' => TRUE,
        'weights' => ['preprocess_index' => -6, 'preprocess_query' => -6],
      ],
    ];
    $fields = [
      'name' => [
        'label' => 'Name',
        'datasource_id' => 'entity:entity_test_mulrev_changed',
        'property_path' => 'name',
        'type' => 'text',
      ],
    ];
    if ($transliterate) {
      $processors['transliteration'] = [
        'all_fields' => TRUE,
        'weights' => ['preprocess_index' => -20, 'preprocess_query' => -20],
      ];
      // Without transliteration, the 4-byte characters in this string field
      // would not fit the backend's utf8mb3 column.
      $fields['name_string'] = [
        'label' => 'Name (string)',
        'datasource_id' => 'entity:entity_test_mulrev_changed',
        'property_path' => 'name',
        'type' => 'string',
      ];
    }
    Index::create([
      'id' => $id,
      'name' => $id,
      'status' => TRUE,
      'server' => 'test_server',
      'datasource_settings' => [
        'entity:entity_test_mulrev_changed' => [],
      ],
      'tracker_settings' => [
        'default' => [],
      ],
      'field_settings' => $fields,
      'processor_settings' => $processors,
      'options' => [
        'cron_limit' => -1,
        'index_directly' => FALSE,
      ],
    ])->save();
  }

  /**
   * Returns the sorted entity IDs found by a fulltext search.
   */
  protected function search(string $keys): array {
    $ids = [];
    foreach (Index::load('test_index')->query()->keys($keys)->execute() as $item) {
      $ids[] = (int) $item->getOriginalObject()->getValue()->id();
    }
    sort($ids);
    return $ids;
  }

}
