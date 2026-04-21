<?php

namespace Drupal\Tests\search_api\Kernel\Datasource;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\Tests\search_api\Kernel\PostRequestIndexingTrait;
use Drupal\Tests\search_api\Kernel\TestLogger;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests correct functionality of the content entity datasource.
 *
 * @coversDefaultClass \Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class NodeTrackingTest extends KernelTestBase {

  use PostRequestIndexingTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'node',
    'system',
    'user',
    'search_api',
    'search_api_test',
  ];

  /**
   * The test index used in this test.
   */
  protected IndexInterface $index;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['language', 'search_api']);

    // Create some languages.
    for ($i = 0; $i < 2; ++$i) {
      ConfigurableLanguage::create([
        'id' => 'l' . $i,
        'label' => 'language - ' . $i,
        'weight' => $i,
      ])->save();
    }
    $this->container->get('language_manager')->reset();

    // Create a test index.
    Server::create([
      'name' => 'Test Server',
      'id' => 'test_server',
      'backend' => 'search_api_test',
    ])->save();
    $this->index = Index::create([
      'name' => 'Test Index',
      'id' => 'test_index',
      'status' => TRUE,
      'server' => 'test_server',
      'datasource_settings' => [
        'entity:node' => [
          'languages' => [
            'default' => TRUE,
            'selected' => ['l0'],
          ],
        ],
      ],
      'tracker_settings' => [
        'default' => [],
      ],
      'processor_settings' => [
        'content_access' => [],
      ],
      'field_settings' => [
        'node_grants' => [
          'label' => 'Node access information',
          'type' => 'string',
          'property_path' => 'search_api_node_grants',
          'indexed_locked' => TRUE,
          'type_locked' => TRUE,
          'hidden' => TRUE,
        ],
        'status' => [
          'label' => 'Publishing status',
          'type' => 'boolean',
          'datasource_id' => 'entity:node',
          'property_path' => 'status',
          'indexed_locked' => TRUE,
          'type_locked' => TRUE,
        ],
        'uid' => [
          'label' => 'Author ID',
          'type' => 'integer',
          'datasource_id' => 'entity:node',
          'property_path' => 'uid',
          'indexed_locked' => TRUE,
          'type_locked' => TRUE,
        ],
      ],
      'options' => [
        'index_directly' => TRUE,
      ],
    ]);
    $this->index->save();
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);

    // Set a logger that will throw exceptions when warnings/errors are logged.
    $logger = new TestLogger('');
    $container->set('logger.factory', $logger);
    $container->set('logger.channel.search_api', $logger);
  }

  /**
   * Tests that editing an entity of a disabled language produces no error.
   *
   * @covers ::trackEntityChange
   */
  public function testIgnoredLanguageEntityUpdate(): void {
    $entity = Node::create([
      'nid' => 1,
      'type' => 'node',
      'langcode' => 'l0',
      'title' => 'Language 0 node',
    ]);
    $entity->save();
    $entity->addTranslation('l1')->set('title', 'Language 1 node')->save();

    $this->triggerPostRequestIndexing();
    $this->assertTrue(TRUE);
  }

}
