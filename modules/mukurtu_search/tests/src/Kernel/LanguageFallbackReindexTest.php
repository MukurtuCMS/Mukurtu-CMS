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

    require_once $this->root . '/core/includes/form.inc';
    $batch = &batch_get();
    $batch = [];
  }

  /**
   * Adding a language batches the reindex, so old content shows right away.
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
    $this->assertSame(['reindex_test_index'], $this->batchedIndexIds(), 'Adding a language should batch the index, so content shows without waiting for cron.');

    $this->runBatch();
    $this->assertSame(0, $this->remaining(), 'The batch should index every queued item.');
    $this->assertSame(1, $this->countForLanguage('fr'), 'After the batch, content indexed before French was added should fall back for French.');
  }

  /**
   * Saving several languages in one request batches each index once.
   */
  public function testOneBatchPerIndexPerRequest(): void {
    EntityTestMul::create(['name' => 'Item', 'langcode' => 'en'])->save();
    $this->index->indexItems();

    ConfigurableLanguage::createFromLangcode('fr')->save();
    ConfigurableLanguage::createFromLangcode('es')->save();
    $french = ConfigurableLanguage::load('fr');
    $french->setWeight($french->getWeight() + 5)->save();

    $this->assertSame(['reindex_test_index'], $this->batchedIndexIds(), 'Reordering languages saves each one, which should still batch each index only once.');
  }

  /**
   * An empty index isn't batched.
   *
   * Search API's batch reports "Couldn't index items" when it indexes
   * nothing, which would show an error every time a language is added to a
   * site with an empty index.
   */
  public function testNoBatchForEmptyIndex(): void {
    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->assertSame([], $this->batchedIndexIds(), 'An index with nothing to reindex should not be batched.');
  }

  /**
   * Languages created during site install don't start a batch.
   */
  public function testNoBatchDuringInstall(): void {
    EntityTestMul::create(['name' => 'Item', 'langcode' => 'en'])->save();
    $this->index->indexItems();

    $GLOBALS['install_state'] = ['installation_finished' => FALSE];
    try {
      ConfigurableLanguage::createFromLangcode('fr')->save();
    }
    finally {
      unset($GLOBALS['install_state']);
    }
    $this->assertSame([], $this->batchedIndexIds(), 'Site install should not batch indexing.');
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
   * The update hook reindexes existing multilingual sites in full.
   */
  public function testUpdateHookReindexes(): void {
    // Index more items than one pass handles, so the hook needs several.
    $this->index->setOption('cron_limit', 2)->save();
    for ($i = 0; $i < 5; $i++) {
      EntityTestMul::create(['name' => "Item $i", 'langcode' => 'en'])->save();
    }
    $this->index->indexItems();
    $this->assertSame(0, $this->remaining());

    // Add French without the hook's own reindex, to leave the stale index
    // data an existing site has.
    \Drupal::configFactory()->getEditable('language.entity.fr')->setData(ConfigurableLanguage::createFromLangcode('fr')->toArray())->save();
    \Drupal::languageManager()->reset();
    $this->assertCount(2, \Drupal::languageManager()->getLanguages());
    $this->assertSame(0, $this->countForLanguage('fr'), 'Sanity check: the stale index has no French fallback values.');

    require_once dirname(__DIR__, 4) . '/mukurtu_core/mukurtu_core.install';
    $sandbox = [];
    $passes = 0;
    do {
      $message = mukurtu_core_update_40213($sandbox);
      $passes++;
    } while (($sandbox['#finished'] ?? 1) < 1 && $passes < 20);

    $this->assertGreaterThan(1, $passes, 'Sanity check: the update needed more than one pass.');
    $this->assertSame(0, $this->remaining(), 'The update hook should index every item, not leave them for cron.');
    $this->assertSame(5, $this->countForLanguage('fr'), 'After the update, existing content should fall back for French.');
    $this->assertSame('Reindexed 1 search indexes.', $message);
  }

  /**
   * Lists the index IDs in the batch sets queued this request.
   */
  protected function batchedIndexIds(): array {
    $ids = [];
    foreach (batch_get()['sets'] ?? [] as $set) {
      foreach ($set['operations'] as [, $arguments]) {
        $ids[] = $arguments[0]->id();
      }
    }
    return $ids;
  }

  /**
   * Runs the queued batch operations, as the form submit would.
   */
  protected function runBatch(): void {
    foreach (batch_get()['sets'] ?? [] as $set) {
      foreach ($set['operations'] as [$callback, $arguments]) {
        $context = ['sandbox' => [], 'results' => [], 'finished' => 1, 'message' => ''];
        do {
          $callback(...[...$arguments, &$context]);
        } while ($context['finished'] < 1);
      }
    }
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
