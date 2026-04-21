<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api\Kernel\Datasource;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Language\LanguageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Utility\TrackingHelper;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\search_api\Kernel\PostRequestIndexingTrait;
use Drupal\Tests\search_api\Kernel\TestLogger;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that changes in related entities are correctly tracked.
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class ReferencedEntitiesReindexingTest extends KernelTestBase {

  use PostRequestIndexingTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'node',
    'field',
    'system',
    'search_api',
    'search_api_test',
    'search_api_test_example_content_references',
  ];

  /**
   * The search index used for this test.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * Entities created for this test, keyed by human-readable string keys.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $entities = [];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('search_api_task');
    $this->installConfig([
      'search_api',
      'search_api_test_example_content_references',
    ]);

    // Do not use a batch for tracking the initial items after creating an
    // index when running the tests via the GUI. Otherwise, it seems Drupal's
    // Batch API gets confused and the test fails.
    if (!Utility::isRunningInCli()) {
      \Drupal::state()->set('search_api_use_tracking_batch', FALSE);
    }

    Server::create([
      'id' => 'server',
      'backend' => 'search_api_test',
    ])->save();
    $this->index = Index::create([
      'id' => 'index',
      'tracker_settings' => [
        'default' => [],
      ],
      'datasource_settings' => [
        'entity:node' => [
          'bundles' => [
            'default' => FALSE,
            'selected' => ['grandparent', 'parent'],
          ],
        ],
        'entity:user' => [],
      ],
      'server' => 'server',
      'field_settings' => [
        'child_indexed' => [
          'label' => 'Child > Indexed',
          'datasource_id' => 'entity:node',
          'property_path' => 'entity_reference:entity:indexed',
          'type' => 'text',
        ],
        'grandchild_indexed' => [
          'label' => 'Parent > Child > Indexed',
          'datasource_id' => 'entity:node',
          'property_path' => 'parent_reference:entity:entity_reference:entity:indexed',
          'type' => 'text',
        ],
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
    $container->set('logger.channel.search_api_db', $logger);
  }

  /**
   * Tests correct tracking of changes in referenced entities.
   *
   * @param array $child_map
   *   Map of the child nodes to create. It should be compatible with the
   *   ::createEntitiesFromMap().
   * @param array $updates
   *   A list of updates to child entities to execute. It should be keyed by the
   *   machine-name of the child entities. Value can be either FALSE (to remove
   *   an entity) or a list of the new raw values to apply to the entity.
   * @param array $expected
   *   A list of search items that should be marked for reindexing.
   *
   * @dataProvider referencedEntityChangedDataProvider
   */
  public function testReferencedEntityChanged(array $child_map, array $updates, array $expected) {
    $child_map += [
      'second_child' => [
        'title' => 'Second child',
      ],
    ];
    $this->createEntitiesFromMap($child_map, 'child');
    $parent_map = [
      'parent' => [
        'title' => 'Parent',
        'entity_reference' => ['second_child', 'child'],
      ],
      'unrelated_parent' => [
        'title' => 'Unrelated parent',
      ],
    ];
    $this->createEntitiesFromMap($parent_map, 'parent');
    $grandparent_map = [
      'grandparent' => [
        'title' => 'Grandparent',
        'parent_reference' => ['parent', 'unrelated_parent'],
      ],
    ];
    $this->createEntitiesFromMap($grandparent_map, 'grandparent');

    $this->index->indexItems();
    $tracker = $this->index->getTrackerInstance();
    $this->assertEquals([], $tracker->getRemainingItems());

    // Now let's execute updates.
    foreach ($updates as $key => $field_updates) {
      if ($field_updates === FALSE) {
        $this->entities[$key]->delete();
      }
      else {
        foreach ($field_updates as $field => $items) {
          $this->entities[$key]->get($field)->setValue($items);
        }

        $this->entities[$key]->save();
      }
    }

    $expected_ids = [];
    foreach ($expected as $key) {
      $expected_ids[] = 'entity:node/' . $this->entities[$key]->id() . ':en';
    }
    $this->assertEquals($expected_ids, $tracker->getRemainingItems());

    // Make sure that no unknown items were queued for post-request indexing.
    $this->triggerPostRequestIndexing();
  }

  /**
   * Provides test data for testReferencedEntityChanged().
   *
   * @return array[]
   *   An array of argument arrays for testReferencedEntityChanged().
   *
   * @see \Drupal\Tests\search_api\Kernel\ReferencedEntitiesReindexingTest::testReferencedEntityChanged()
   */
  public static function referencedEntityChangedDataProvider(): array {
    $tests = [];
    foreach (['child', 'unrelated'] as $child) {
      foreach (['indexed', 'not_indexed'] as $field) {
        if ($child == 'child' && $field == 'indexed') {
          // This is how Search API represents our "parent" node in its tracking
          // data.
          $expected = ['parent', 'grandparent'];
        }
        else {
          $expected = [];
        }

        $tests["changing value of $field field within the $child entity"] = [
          [
            'child' => [
              'title' => 'Child',
              'indexed' => 'Original indexed value',
              'not_indexed' => 'Original not indexed value.',
            ],
            'unrelated' => [
              'title' => 'Unrelated child',
              'indexed' => 'Original indexed value',
              'not_indexed' => 'Original not indexed value.',
            ],
          ],
          [
            $child => [
              $field => "New $field value.",
            ],
          ],
          $expected,
        ];

        $tests["appending value of $field field within the $child entity"] = [
          [
            'child' => [
              'title' => 'Child',
              'indexed' => 'Original indexed value',
            ],
            'unrelated' => [
              'title' => 'Unrelated child',
              'indexed' => 'Original indexed value',
            ],
          ],
          [
            $child => [
              $field => "New $field value.",
            ],
          ],
          $expected,
        ];

        $tests["removing value of $field field within the $child entity"] = [
          [
            'child' => [
              'title' => 'Child',
              'indexed' => 'Original indexed value',
              'not_indexed' => 'Original not indexed value.',
            ],
            'unrelated' => [
              'title' => 'Unrelated child',
              'indexed' => 'Original indexed value',
              'not_indexed' => 'Original not indexed value.',
            ],
          ],
          [
            $child => [
              $field => [],
            ],
          ],
          $expected,
        ];
      }

      $tests["removing the $child entity"] = [
        [
          'child' => [
            'title' => 'Child',
            'indexed' => 'Original indexed value',
            'not_indexed' => 'Original not indexed value.',
          ],
          'unrelated' => [
            'title' => 'Unrelated child',
            'indexed' => 'Original indexed value',
            'not_indexed' => 'Original not indexed value.',
          ],
        ],
        [
          $child => FALSE,
        ],
        $child == 'child' ? ['parent', 'grandparent'] : [],
      ];
    }

    return $tests;
  }

  /**
   * Creates a list of entities with the given fields.
   *
   * @param array[] $entity_fields
   *   Map of entities to create. It should be keyed by a machine-friendly name.
   *   Values of this map should be sub-arrays that represent raw values to
   *   supply into the entity's fields when creating it.
   * @param string $bundle
   *   Bundle to utilize when creating entities from the $map array.
   */
  protected function createEntitiesFromMap(array $entity_fields, string $bundle): void {
    foreach ($entity_fields as $key => $fields) {
      $reference_fields = ['entity_reference', 'parent_reference'];
      foreach ($reference_fields as $reference_field) {
        if (isset($fields[$reference_field])) {
          $ids = [];
          foreach ((array) $fields[$reference_field] as $referenced_key) {
            $ids[] = $this->entities[$referenced_key]->id();
          }
          $fields[$reference_field] = $ids;
        }
      }
      $fields['type'] = $bundle;
      $this->entities[$key] = Node::create($fields);
      $this->entities[$key]->save();
    }
  }

  /**
   * Tests whether relationships are correctly separated between datasources.
   *
   * @see https://www.drupal.org/node/3178941
   */
  public function testUnrelatedDatasourceUnaffected() {
    // First, check whether the tracking helper correctly includes "datasource"
    // keys with all foreign relationship entries.
    $tracking_helper = \Drupal::getContainer()
      ->get('search_api.tracking_helper');
    $method = new \ReflectionMethod(TrackingHelper::class, 'getForeignEntityRelationsMap');
    /** @see \Drupal\search_api\Utility\TrackingHelper::getForeignEntityRelationsMap() */
    $map = $method->invoke($tracking_helper, $this->index);
    usort($map, function (array $a, array $b): int {
      $field = 'property_path_to_foreign_entity';
      return $a[$field] <=> $b[$field];
    });
    $expected = [
      [
        'datasource' => 'entity:node',
        'entity_type' => 'node',
        // Note: It's unspecified that this array has string keys, only its
        // values are important. Still, it's easier to just reflect the current
        // implementation, when checking for equality.
        'bundles' => ['child' => 'child'],
        'property_path_to_foreign_entity' => 'entity_reference:entity',
        'field_name' => 'indexed',
      ],
      [
        'datasource' => 'entity:node',
        'entity_type' => 'node',
        'bundles' => ['parent' => 'parent'],
        'property_path_to_foreign_entity' => 'parent_reference:entity',
        'field_name' => 'entity_reference',
      ],
      [
        'datasource' => 'entity:node',
        'entity_type' => 'node',
        'bundles' => ['child' => 'child'],
        'property_path_to_foreign_entity' => 'parent_reference:entity:entity_reference:entity',
        'field_name' => 'indexed',
      ],
    ];
    $this->assertEquals($expected, $map);

    // Then, check whether datasources correctly ignore relationships from other
    // datasources, or that they at least don't lead to an exception/error.
    $datasource = $this->index->getDatasource('entity:user');
    $this->createEntitiesFromMap([
      'child' => [
        'title' => 'Child',
        'indexed' => 'Indexed value',
        'not_indexed' => 'Not indexed value.',
      ],
    ], 'child');
    $child = reset($this->entities);
    $original_child = clone $child;
    $child->get('indexed')->setValue(['New value']);
    $result = $datasource->getAffectedItemsForEntityChange($child, $map, $original_child);
    $this->assertEquals([], $result);

    // Change foreign relationships map slightly to trigger #3178941 on purpose.
    $map[0]['property_path_to_foreign_entity'] = 'entity_reference:entity';
    $result = $datasource->getAffectedItemsForEntityChange($child, $map, $original_child);
    $this->assertEquals([], $result);
  }

  /**
   * Tests that the tracking of changes in referenced entities can be disabled.
   */
  public function testDisableOption() {
    $this->index->setOption('track_changes_in_references', FALSE);
    $this->index->save();

    $child_map = [
      'child' => [
        'title' => 'Child',
        'indexed' => 'Original indexed value',
        'not_indexed' => 'Original not indexed value.',
      ],
      'unrelated' => [
        'title' => 'Unrelated child',
        'indexed' => 'Original indexed value',
        'not_indexed' => 'Original not indexed value.',
      ],
    ];
    $this->createEntitiesFromMap($child_map, 'child');
    $parent_map = [
      'parent' => [
        'title' => 'Parent',
        'entity_reference' => 'child',
      ],
    ];
    $this->createEntitiesFromMap($parent_map, 'parent');
    $grandparent_map = [
      'grandparent' => [
        'title' => 'Grandparent',
        'parent_reference' => 'parent',
      ],
    ];
    $this->createEntitiesFromMap($grandparent_map, 'grandparent');

    $this->index->indexItems();
    $tracker = $this->index->getTrackerInstance();
    $this->assertEquals([], $tracker->getRemainingItems());

    // Now let's execute updates.
    $this->entities['child']->get('indexed')->setValue(['New indexed value.']);
    $this->entities['child']->save();

    $this->assertEquals([], $tracker->getRemainingItems());
  }

  /**
   * Tests that tracking also works for entities with langcode "und" or "zxx".
   */
  public function testParentEntityWithoutLanguage() {
    $child_map = [
      'child' => [
        'title' => 'Child',
        'indexed' => 'Original indexed value',
        'not_indexed' => 'Original not indexed value.',
      ],
      'unrelated' => [
        'title' => 'Unrelated child',
        'indexed' => 'Original indexed value',
        'not_indexed' => 'Original not indexed value.',
      ],
    ];
    $this->createEntitiesFromMap($child_map, 'child');
    $parent_map = [
      'parent' => [
        'title' => 'Parent',
        'entity_reference' => 'child',
        'langcode' => LanguageInterface::LANGCODE_NOT_APPLICABLE,
      ],
    ];
    $this->createEntitiesFromMap($parent_map, 'parent');
    $grandparent_map = [
      'grandparent' => [
        'title' => 'Grandparent',
        'parent_reference' => 'parent',
        'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      ],
    ];
    $this->createEntitiesFromMap($grandparent_map, 'grandparent');

    $this->index->indexItems();
    $tracker = $this->index->getTrackerInstance();
    $this->assertEquals([], $tracker->getRemainingItems());

    // Now let's execute updates.
    $this->entities['child']->get('indexed')->setValue(['New indexed value.']);
    $this->entities['child']->save();

    $expected = ['entity:node/3:zxx', 'entity:node/4:und'];
    $this->assertEquals($expected, $tracker->getRemainingItems());
  }

}
