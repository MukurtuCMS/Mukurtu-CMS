<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_search\Kernel;

use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Adding or reordering a language reindexes language fallback data.
 *
 * Search API's language_with_fallback values are computed at index time, so
 * content indexed before a language existed has no value for it, and the
 * browse views' filter hides that content in the new language. Reported on
 * #2347: only content created after French was added showed on /fr/browse.
 *
 * @see \Drupal\mukurtu_search\Hook\LanguageFallbackReindexHooks
 */
#[Group('mukurtu_search')]
class LanguageFallbackReindexTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'language',
    'entity_test',
    'views',
    'search_api',
    'search_api_db',
    'mukurtu_search',
  ];

  /**
   * The index under test.
   */
  protected IndexInterface $index;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('entity_test_mul');
    $this->installEntitySchema('user');
    $this->installConfig(['search_api', 'language']);

    Server::create([
      'id' => 'reindex_test_server',
      'name' => 'Reindex test server',
      'backend' => 'search_api_db',
      'backend_config' => ['database' => 'default:default'],
      'status' => TRUE,
    ])->save();

    Index::create([
      'id' => 'reindex_test_index',
      'name' => 'Reindex test index',
      'status' => TRUE,
      'server' => 'reindex_test_server',
      'datasource_settings' => ['entity:entity_test_mul' => []],
      'tracker_settings' => ['default' => []],
      'processor_settings' => ['language_with_fallback' => []],
      'field_settings' => [
        'language_with_fallback' => [
          'label' => 'Language (with fallback)',
          'property_path' => 'language_with_fallback',
          'type' => 'string',
        ],
      ],
      'options' => ['index_directly' => FALSE],
    ])->save();

    $this->index = Index::load('reindex_test_index');
  }

  /**
   * Content indexed before a language is added shows in it after reindexing.
   */
  public function testAddingLanguageReindexes(): void {
    EntityTestMul::create(['name' => 'Indexed before French', 'langcode' => 'en'])->save();
    $this->index->indexItems();
    $this->assertSame(0, $this->remaining(), 'Sanity check: everything is indexed before French is added.');

    ConfigurableLanguage::createFromLangcode('fr')->save();

    // The stale index data is what the reviewer saw: nothing falls back to
    // the old content for French until it is reindexed.
    $this->assertSame(0, $this->countForLanguage('fr'), 'Sanity check: the stale index has no French fallback values.');
    $this->assertGreaterThan(0, $this->remaining(), 'Adding a language should queue the index for reindexing.');

    $this->index->indexItems();
    $this->assertSame(1, $this->countForLanguage('fr'), 'After reindexing, content indexed before French was added should fall back for French.');
  }

  /**
   * A weight change reindexes, but other language edits don't.
   */
  public function testOnlyWeightChangesReindex(): void {
    ConfigurableLanguage::createFromLangcode('fr')->save();
    EntityTestMul::create(['name' => 'Item', 'langcode' => 'en'])->save();
    $this->index->indexItems();
    $this->assertSame(0, $this->remaining());

    $french = ConfigurableLanguage::load('fr');
    $french->setName('Français')->save();
    $this->assertSame(0, $this->remaining(), 'Renaming a language should not reindex.');

    $french->setWeight($french->getWeight() + 5)->save();
    $this->assertGreaterThan(0, $this->remaining(), 'Changing a language weight changes fallback order and should reindex.');
  }

  /**
   * The update hook queues a reindex on existing multilingual sites.
   */
  public function testUpdateHookReindexes(): void {
    ConfigurableLanguage::createFromLangcode('fr')->save();
    EntityTestMul::create(['name' => 'Item', 'langcode' => 'en'])->save();
    $this->index->indexItems();
    $this->assertSame(0, $this->remaining());

    require_once dirname(__DIR__, 4) . '/mukurtu_core/mukurtu_core.install';
    $message = mukurtu_core_update_40210();

    $this->assertGreaterThan(0, $this->remaining(), 'The update hook should queue indexes with language fallback data for reindexing.');
    $this->assertSame('Queued 1 search indexes for reindexing.', $message);
  }

  /**
   * Counts the items still waiting to be indexed.
   */
  protected function remaining(): int {
    return $this->index->getTrackerInstance()->getRemainingItemsCount();
  }

  /**
   * Counts the indexed items that show in the given language.
   */
  protected function countForLanguage(string $langcode): int {
    return (int) $this->index->query()
      ->addCondition('language_with_fallback', [$langcode], 'IN')
      ->execute()
      ->getResultCount();
  }

}
