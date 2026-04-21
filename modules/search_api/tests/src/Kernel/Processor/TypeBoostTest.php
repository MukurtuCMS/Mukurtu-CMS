<?php

namespace Drupal\Tests\search_api\Kernel\Processor;

use Drupal\Core\Form\FormState;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Utility\Utility;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the "Type-specific boosting" processor.
 *
 * @group search_api
 *
 * @coversDefaultClass \Drupal\search_api\Plugin\search_api\processor\TypeBoost
 */
#[RunTestsInSeparateProcesses]
class TypeBoostTest extends ProcessorTestBase {

  /**
   * The processor used for this test.
   *
   * @var \Drupal\search_api\Plugin\search_api\processor\TypeBoost
   */
  protected $processor;

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('type_boost');

    // Create an article node type, if not already present.
    if (!NodeType::load('article')) {
      $article_node_type = NodeType::create([
        'type' => 'article',
        'name' => 'Article',
      ]);
      $article_node_type->save();
    }

    // Create a page node type, if not already present.
    if (!NodeType::load('page')) {
      $page_node_type = NodeType::create([
        'type' => 'page',
        'name' => 'Page',
      ]);
      $page_node_type->save();
    }

    // Setup a node index.
    $datasources = \Drupal::getContainer()
      ->get('search_api.plugin_helper')
      ->createDatasourcePlugins($this->index, ['entity:node']);
    $this->index->setDatasources($datasources);
    $this->index->save();
    $this->container
      ->get('search_api.index_task_manager')
      ->addItemsAll($this->index);
    $index_storage = $this->container
      ->get('entity_type.manager')
      ->getStorage('search_api_index');
    $index_storage->resetCache([$this->index->id()]);
    $this->index = $index_storage->load($this->index->id());
  }

  /**
   * Tests that the correct boost is set on items.
   *
   * @covers ::preprocessIndexItems
   */
  public function testEntityBundleBoost() {
    // Enable the processor indexing.
    $processor = $this->index->getProcessor('type_boost');
    $configuration = [
      'boosts' => [
        'entity:node' => [
          'datasource_boost' => Utility::formatBoostFactor(3),
          'bundle_boosts' => [
            'article' => Utility::formatBoostFactor(5),
          ],
        ],
      ],
    ];
    $processor->setConfiguration($configuration);
    $this->index->setProcessors(['type_boost' => $processor]);
    $this->index->save();

    // Create nodes for both node types.
    $nodes = [];
    foreach (['article', 'page', 'article'] as $node_type) {
      $node = Node::create([
        'status' => NodeInterface::PUBLISHED,
        'type' => $node_type,
        'title' => $this->randomString(),
      ]);
      $node->save();
      $nodes[$node->id()] = $node->getTypedData();
    }

    // Prepare and generate Search API items.
    $items = [];
    foreach ($nodes as $nid => $node) {
      $items[] = [
        'datasource' => 'entity:node',
        'item' => $node,
        'item_id' => $nid,
      ];
    }
    $items = $this->generateItems($items);

    // Set a boost on one of the items to check whether it gets overwritten or
    // (correctly) multiplied.
    $items['entity:node/3']->setBoost(2);

    // Preprocess items.
    $this->index->preprocessIndexItems($items);

    // Check boost value on article node.
    $boost_expected = 5;
    $boost_actual = $items['entity:node/1']->getBoost();
    $this->assertEquals($boost_expected, $boost_actual);

    // Check boost value on page node.
    $boost_expected = 3;
    $boost_actual = $items['entity:node/2']->getBoost();
    $this->assertEquals($boost_expected, $boost_actual);

    // Check boost value on article node with pre-existing boost.
    $boost_expected = 10;
    $boost_actual = $items['entity:node/3']->getBoost();
    $this->assertEquals($boost_expected, $boost_actual);
  }

  /**
   * Tests that default values are correct in the config form.
   */
  public function testConfigFormDefaultValues() {
    $form = $this->processor->buildConfigurationForm([], new FormState());

    $this->assertEquals(Utility::formatBoostFactor(1), $form['boosts']['entity:node']['datasource_boost']['#default_value']);
    $this->assertEquals('', $form['boosts']['entity:node']['bundle_boosts']['article']['#default_value']);
    $this->assertEquals('', $form['boosts']['entity:node']['bundle_boosts']['page']['#default_value']);

    $configuration = [
      'boosts' => [
        'entity:node' => [
          'datasource_boost' => Utility::formatBoostFactor(3),
          'bundle_boosts' => [
            'article' => Utility::formatBoostFactor(0),
          ],
        ],
      ],
    ];
    $this->processor->setConfiguration($configuration);

    $form = $this->processor->buildConfigurationForm([], new FormState());

    $this->assertEquals(Utility::formatBoostFactor(3), $form['boosts']['entity:node']['datasource_boost']['#default_value']);
    $this->assertEquals(Utility::formatBoostFactor(0), $form['boosts']['entity:node']['bundle_boosts']['article']['#default_value']);
    $this->assertEquals('', $form['boosts']['entity:node']['bundle_boosts']['page']['#default_value']);

    $configuration = [
      'boosts' => [
        'entity:node' => [
          'datasource_boost' => Utility::formatBoostFactor(2),
          'bundle_boosts' => [
            'article' => Utility::formatBoostFactor(3),
            'page' => Utility::formatBoostFactor(1.5),
          ],
        ],
      ],
    ];
    $this->processor->setConfiguration($configuration);

    $form = $this->processor->buildConfigurationForm([], new FormState());

    $this->assertEquals(Utility::formatBoostFactor(2), $form['boosts']['entity:node']['datasource_boost']['#default_value']);
    $this->assertEquals(Utility::formatBoostFactor(3), $form['boosts']['entity:node']['bundle_boosts']['article']['#default_value']);
    $this->assertEquals(Utility::formatBoostFactor(1.5), $form['boosts']['entity:node']['bundle_boosts']['page']['#default_value']);
  }

}
