<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_collection\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\mukurtu_collection\CollectionOrder;
use Drupal\mukurtu_collection\Plugin\search_api\processor\CollectionDisplayWeight;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Runs the shipped /collections sorts against a database-backed index.
 *
 * The custom order has to be applied by the search query, not after it, or
 * it would only reorder the collections on the current page. These tests
 * page through the results to check the order holds across pages.
 */
#[CoversClass(CollectionDisplayWeight::class)]
#[CoversClass(CollectionOrder::class)]
#[Group('mukurtu_collection')]
class CollectionDisplayOrderViewTest extends CollectionTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api',
    'search_api_db',
  ];

  /**
   * The test index ID.
   */
  protected const INDEX_ID = 'mukurtu_collection_index';

  /**
   * Collection node IDs, keyed by title.
   *
   * @var int[]
   */
  protected array $ids = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');
    $this->installConfig(['search_api']);

    $this->config(CollectionOrder::CONFIG_NAME)
      ->set('sort_mode', CollectionOrder::MODE_ALPHABETICAL)
      ->set('order', [])
      ->save();

    Server::create([
      'id' => 'collection_order_test_server',
      'name' => 'Collection order test server',
      'backend' => 'search_api_db',
      'backend_config' => ['database' => 'default:default'],
      'status' => TRUE,
    ])->save();

    // The shipped index ID, so the order service reindexes it.
    Index::create([
      'id' => static::INDEX_ID,
      'name' => 'Collection order test index',
      'status' => TRUE,
      'server' => 'collection_order_test_server',
      'datasource_settings' => [
        'entity:node' => ['bundles' => ['default' => FALSE, 'selected' => ['collection']]],
      ],
      'tracker_settings' => ['default' => []],
      'processor_settings' => ['mukurtu_collection_display_weight' => []],
      'field_settings' => [
        CollectionOrder::FIELD_NAME => [
          'label' => 'Collection display weight',
          'datasource_id' => 'entity:node',
          'property_path' => CollectionOrder::FIELD_NAME,
          'type' => 'integer',
        ],
        // Shipped as a fulltext field, which the database backend can still
        // sort on.
        'title' => [
          'label' => 'Title',
          'datasource_id' => 'entity:node',
          'property_path' => 'title',
          'type' => 'text',
        ],
      ],
      'options' => ['index_directly' => FALSE],
    ])->save();

    foreach (['Echo', 'alpha', 'Delta', 'Charlie', 'Bravo'] as $title) {
      $collection = $this->buildCollection($title);
      $collection->save();
      $this->ids[$title] = (int) $collection->id();
    }
    Index::load(static::INDEX_ID)->indexItems();

    // The shipped sorts, minus the parent ID tie-breaker, which this index
    // doesn't have.
    $shipped = Yaml::decode(file_get_contents(dirname(__DIR__, 4) . '/mukurtu_browse/config/install/views.view.mukurtu_browse_collections.yml'));
    $sorts = $shipped['display']['default']['display_options']['sorts'];
    unset($sorts['parent_collection_id']);
    $this->assertSame([CollectionOrder::FIELD_NAME, 'title'], array_keys($sorts));

    View::create([
      'id' => 'collection_order_test',
      'base_table' => 'search_api_index_' . static::INDEX_ID,
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_options' => [
            'sorts' => $sorts,
            'pager' => ['type' => 'full', 'options' => ['items_per_page' => 2]],
            // Search API's Views query drops rows the test user can't see.
            'query' => ['type' => 'search_api_query', 'options' => ['skip_access' => TRUE]],
          ],
        ],
      ],
    ])->save();
  }

  /**
   * Gets collection titles in view order, one page at a time.
   *
   * @return string[][]
   *   Titles for each page.
   */
  protected function pages(): array {
    $titles = array_flip($this->ids);
    $pages = [];
    for ($page = 0; $page < 3; $page++) {
      $view = Views::getView('collection_order_test');
      $view->setCurrentPage($page);
      $view->execute();
      $pages[] = array_map(
        fn ($row): string => $titles[(int) $row->_item->getOriginalObject()->getValue()->id()],
        $view->result,
      );
    }
    return $pages;
  }

  /**
   * Alphabetical mode lists collections by title.
   */
  public function testAlphabetical(): void {
    $this->assertSame([['alpha', 'Bravo'], ['Charlie', 'Delta'], ['Echo']], $this->pages());
  }

  /**
   * A custom order holds across pages, with unplaced collections last.
   */
  public function testCustomOrderAcrossPages(): void {
    $order = $this->container->get('mukurtu_collection.collection_order');
    $order->save(CollectionOrder::MODE_CUSTOM, [$this->ids['Delta'], $this->ids['Bravo'], $this->ids['Echo']]);
    Index::load(static::INDEX_ID)->indexItems();

    $this->assertSame([['Delta', 'Bravo'], ['Echo', 'alpha'], ['Charlie']], $this->pages());

    // Switching back reindexes too.
    $order->save(CollectionOrder::MODE_ALPHABETICAL, $order->getOrder());
    Index::load(static::INDEX_ID)->indexItems();

    $this->assertSame([['alpha', 'Bravo'], ['Charlie', 'Delta'], ['Echo']], $this->pages());
  }

}
