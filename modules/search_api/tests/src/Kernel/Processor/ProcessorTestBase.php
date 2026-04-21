<?php

namespace Drupal\Tests\search_api\Kernel\Processor;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\search_api\Kernel\TestLogger;

/**
 * Provides a base class for Drupal unit tests for processors.
 */
abstract class ProcessorTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'node',
    'field',
    'search_api',
    'search_api_db',
    'search_api_test',
    'comment',
    'text',
    'system',
  ];

  /**
   * The processor used for this test.
   *
   * @var \Drupal\search_api\Processor\ProcessorInterface
   */
  protected $processor;

  /**
   * The search index used for this test.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * The search server used for this test.
   *
   * @var \Drupal\search_api\ServerInterface
   */
  protected $server;

  /**
   * The test logger.
   */
  protected TestLogger $logger;

  /**
   * Performs setup tasks before each individual test method is run.
   *
   * Installs commonly used schemas and sets up a search server and an index,
   * with the specified processor enabled.
   *
   * @param string|null $processor
   *   (optional) The plugin ID of the processor that should be set up for
   *   testing.
   */
  public function setUp($processor = NULL): void {
    parent::setUp();

    $this->installSchema('node', ['node_access']);
    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('search_api_task');
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installConfig(['field']);
    $this->installConfig('search_api');

    // Do not use a batch for tracking the initial items after creating an
    // index when running the tests via the GUI. Otherwise, it seems Drupal's
    // Batch API gets confused and the test fails.
    if (!Utility::isRunningInCli()) {
      \Drupal::state()->set('search_api_use_tracking_batch', FALSE);
    }

    $this->server = Server::create([
      'id' => 'server',
      'name' => 'Server & Name',
      'status' => TRUE,
      'backend' => 'search_api_db',
      'backend_config' => [
        'min_chars' => 3,
        'database' => 'default:default',
      ],
    ]);
    $this->server->save();

    $this->index = Index::create([
      'id' => 'index',
      'name' => 'Index name',
      'status' => TRUE,
      'datasource_settings' => [
        'entity:comment' => [],
        'entity:node' => [],
      ],
      'server' => 'server',
      'tracker_settings' => [
        'default' => [],
      ],
    ]);
    $this->index->setServer($this->server);

    $field_subject = new Field($this->index, 'subject');
    $field_subject->setType('text');
    $field_subject->setPropertyPath('subject');
    $field_subject->setDatasourceId('entity:comment');
    $field_subject->setLabel('Subject');

    $field_title = new Field($this->index, 'title');
    $field_title->setType('text');
    $field_title->setPropertyPath('title');
    $field_title->setDatasourceId('entity:node');
    $field_title->setLabel('Title');

    $this->index->addField($field_subject);
    $this->index->addField($field_title);

    if ($processor) {
      $this->processor = \Drupal::getContainer()
        ->get('search_api.plugin_helper')
        ->createProcessorPlugin($this->index, $processor);
      $this->index->addProcessor($this->processor);
    }
    $this->index->save();
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);

    // Set a logger that will throw exceptions when warnings/errors are logged.
    $this->logger = new TestLogger('');
    $container->set('logger.factory', $this->logger);
    $container->set('logger.channel.search_api', $this->logger);
    $container->set('logger.channel.search_api_db', $this->logger);
  }

  /**
   * Generates some test items.
   *
   * @param array[] $items
   *   Array of items to be transformed into proper search item objects. Each
   *   item in this array is an associative array with the following keys:
   *   - datasource: The datasource plugin ID.
   *   - item: The item object to be indexed.
   *   - item_id: The datasource-specific raw item ID.
   *   - *: Any other keys will be treated as property paths, and their values
   *     as a single value for a field with that property path.
   *
   * @return \Drupal\search_api\Item\ItemInterface[]
   *   The generated test items.
   */
  protected function generateItems(array $items) {
    /** @var \Drupal\search_api\Item\ItemInterface[] $extracted_items */
    $extracted_items = [];
    foreach ($items as $values) {
      $item = $this->generateItem($values);
      $extracted_items[$item->getId()] = $item;
    }

    return $extracted_items;
  }

  /**
   * Generates a single test item.
   *
   * @param array $values
   *   An associative array with the following keys:
   *   - datasource: The datasource plugin ID.
   *   - item: The item object to be indexed.
   *   - item_id: The datasource-specific raw item ID.
   *   - *: Any other keys will be treated as property paths, and their values
   *     as a single value for a field with that property path.
   *
   * @return \Drupal\search_api\Item\Item|\Drupal\search_api\Item\ItemInterface
   *   The generated test item.
   */
  protected function generateItem(array $values) {
    $id = Utility::createCombinedId($values['datasource'], $values['item_id']);
    $item = \Drupal::getContainer()
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $values['item'], $id);
    foreach ([NULL, $values['datasource']] as $datasource_id) {
      foreach ($this->index->getFieldsByDatasource($datasource_id) as $key => $field) {
        /** @var \Drupal\search_api\Item\FieldInterface $field */
        $field = clone $field;
        if (isset($values[$field->getPropertyPath()])) {
          $field->addValue($values[$field->getPropertyPath()]);
        }
        $item->setField($key, $field);
      }
    }
    return $item;
  }

  /**
   * Indexes all (unindexed) items.
   *
   * @return int
   *   The number of successfully indexed items.
   */
  protected function indexItems() {
    return $this->index->indexItems();
  }

}
