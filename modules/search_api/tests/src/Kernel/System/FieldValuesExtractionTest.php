<?php

namespace Drupal\Tests\search_api\Kernel\System;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\Core\TypedData\Plugin\DataType\ItemList;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;
use Drupal\search_api_test_extraction\Plugin\search_api\processor\TestAddPropertiesProcessor;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests extraction of field values, as used during indexing.
 *
 * @coversDefaultClass \Drupal\search_api\Utility\FieldsHelper
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class FieldValuesExtractionTest extends KernelTestBase {

  /**
   * The search index used for testing.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * The test entities used in this test.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $entities = [];

  /**
   * The fields helper service.
   *
   * @var \Drupal\search_api\Utility\FieldsHelperInterface
   */
  protected $fieldsHelper;

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  protected static $modules = [
    'entity_test',
    'field',
    'search_api',
    'search_api_test_extraction',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('user');
    $this->installConfig(['search_api_test_extraction', 'user']);
    $entity_storage = \Drupal::entityTypeManager()
      ->getStorage('entity_test_mulrev_changed');

    $this->entities[0] = $entity_storage->create([
      'type' => 'article',
      'name' => 'Article 1',
      'links' => [],
    ]);
    $this->entities[0]->save();
    $this->entities[1] = $entity_storage->create([
      'type' => 'article',
      'name' => 'Article 2',
      'links' => [],
    ]);
    $this->entities[1]->save();
    $this->entities[2] = $entity_storage->create([
      'type' => 'article',
      'name' => 'Article 3',
      'links' => [
        ['target_id' => $this->entities[0]->id()],
        ['target_id' => $this->entities[1]->id()],
      ],
    ]);
    $this->entities[2]->save();
    $this->entities[3] = $entity_storage->create([
      'type' => 'article',
      'name' => 'Article 4',
      'links' => [
        ['target_id' => $this->entities[0]->id()],
        ['target_id' => $this->entities[2]->id()],
      ],
    ]);
    $this->entities[2]->save();

    user_role_grant_permissions('anonymous', ['view test entity']);

    User::create([
      'id' => $this->entities[0],
      'name' => 'Test user',
    ])->save();

    $this->index = Index::create([
      'field_settings' => [
        'foo' => [
          'type' => 'text',
          'datasource_id' => 'entity:entity_test_mulrev_changed',
          'property_path' => 'name',
        ],
        'bar' => [
          'type' => 'text',
          'property_path' => 'rendered_item',
          'configuration' => [
            'roles' => [
              'anonymous' => 'anonymous',
            ],
            'view_mode' => [
              'entity:entity_test_mulrev_changed' => [
                'article' => 'default',
              ],
            ],
          ],
        ],
      ],
      'datasource_settings' => [
        'entity:entity_test_mulrev_changed' => [],
      ],
    ]);

    $this->fieldsHelper = $this->container->get('search_api.fields_helper');
  }

  /**
   * Tests extraction of field values, as used during indexing.
   *
   * @covers ::extractFields
   * @covers ::extractField
   * @covers ::extractFieldValues
   */
  public function testFieldValuesExtraction() {
    $object = $this->entities[3]->getTypedData();
    /** @var \Drupal\search_api\Item\FieldInterface[][] $fields */
    $fields = [
      'type' => [$this->fieldsHelper->createField($this->index, 'type')],
      'name' => [$this->fieldsHelper->createField($this->index, 'name')],
      'links:entity:name' => [
        $this->fieldsHelper->createField($this->index, 'links'),
        $this->fieldsHelper->createField($this->index, 'links_1'),
      ],
      'links:entity:links:entity:name' => [
        $this->fieldsHelper->createField($this->index, 'links_links'),
      ],
    ];
    $this->fieldsHelper->extractFields($object, $fields);

    $values = [];
    foreach ($fields as $property_path => $property_fields) {
      foreach ($property_fields as $field) {
        $field_values = $field->getValues();
        sort($field_values);
        if (!isset($values[$property_path])) {
          $values[$property_path] = $field_values;
        }
        else {
          $this->assertEquals($field_values, $values[$property_path], 'Second extraction provided the same results as the first.');
        }
      }
    }

    $expected = [
      'type' => ['article'],
      'name' => ['Article 4'],
      'links:entity:name' => [
        'Article 1',
        'Article 3',
      ],
      'links:entity:links:entity:name' => [
        'Article 1',
        'Article 2',
      ],
    ];
    $this->assertEquals($expected, $values, 'Field values were correctly extracted');
  }

  /**
   * Tests extraction of properties, as used in processors or for result lists.
   *
   * @covers ::extractItemValues
   */
  public function testPropertyValuesExtraction() {
    $items['foobar'] = $this->fieldsHelper->createItemFromObject(
      $this->index,
      $this->entities[0]->getTypedData(),
      Utility::createCombinedId('entity:entity_test_mulrev_changed', '0:en')
    );

    $properties = [
      NULL => [
        'rendered_item' => 'a',
        // Since there is no field defined on "aggregated_field" for the index,
        // we won't be able to extract it.
        'aggregated_field' => 'b',
        'search_api_url' => 'c',
      ],
      'entity:entity_test_mulrev_changed' => [
        'name' => 'd',
        'type' => 'e',
        'soul_mate:name' => 'f',
      ],
      'unknown_datasource' => [
        'name' => 'x',
      ],
    ];

    $expected = [
      'foobar' => [
        'a' => [],
        'b' => [],
        'c' => [],
        'd' => [],
        'e' => [],
        'f' => [],
      ],
    ];
    $values = $this->fieldsHelper->extractItemValues($items, $properties, FALSE);
    ksort($values['foobar']);
    $this->assertEquals($expected, $values);

    $expected = [
      'foobar' => [
        // 'a' => 'Tested separately.',
        'b' => [],
        'c' => ['/entity_test_mulrev_changed/manage/1'],
        'd' => ['Article 1'],
        'e' => ['article'],
        'f' => ['Test user'],
      ],
    ];
    $values = $this->fieldsHelper->extractItemValues($items, $properties);
    ksort($values['foobar']);
    $this->assertArrayHasKey('a', $values['foobar']);
    $this->assertNotEmpty($values['foobar']['a']);
    $this->assertStringContainsString('Article 1', $values['foobar']['a'][0]);
    unset($values['foobar']['a']);
    $this->assertEquals($expected, $values);

    $items['foobar']->setFields([
      'aa' => $this->fieldsHelper->createField($this->index, 'aa_foo', [
        'property_path' => 'aggregated_field',
        'values' => [1, 2],
      ]),
      'bb' => $this->fieldsHelper->createField($this->index, 'bb_foo', [
        'property_path' => 'rendered_item',
        'values' => [3],
      ]),
      'cc' => $this->fieldsHelper->createField($this->index, 'cc_foo', [
        'datasource_id' => 'entity:entity_test_mulrev_changed',
        'property_path' => 'type',
        'values' => [4],
      ]),
      'dd' => $this->fieldsHelper->createField($this->index, 'dd_foo', [
        'datasource_id' => 'entity:entity_test_mulrev_changed',
        'property_path' => 'soul_mate:name',
        'values' => [5],
      ]),
    ]);

    $expected = [
      'foobar' => [
        'a' => [3],
        'b' => [1, 2],
        'c' => [],
        'd' => [],
        'e' => [4],
        'f' => [5],
      ],
    ];
    $values = $this->fieldsHelper->extractItemValues($items, $properties, FALSE);
    ksort($values['foobar']);
    $this->assertEquals($expected, $values);

    $expected = [
      'foobar' => [
        'a' => [3],
        'b' => [1, 2],
        'c' => ['/entity_test_mulrev_changed/manage/1'],
        'd' => ['Article 1'],
        'e' => [4],
        'f' => [5],
      ],
    ];
    $values = $this->fieldsHelper->extractItemValues($items, $properties);
    ksort($values['foobar']);
    $this->assertEquals($expected, $values);
  }

  /**
   * Tests extraction of field values from nested complex data structures.
   *
   * @covers ::extractFieldValues
   */
  public function testNestedComplexFieldValuesExtraction() {
    // Complex data definition structure.

    // phpcs:disable Drupal.Commenting.InlineComment.NotCapital
    // data => ListDataDefinition (list) [
    //   itemDefinition => ComplexDataDefinition (map) [
    //     propertyDefinitions => [
    //       id => DataDefinition (string),
    //       values (main property) => ListDataDefinition (list) [
    //         itemDefinition => ComplexDataDefinition (map) [
    //           propertyDefinitions => [
    //             property1 => DataDefinition (string),
    //             property2 (main property) => DataDefinition (string),
    //           ]
    //         ]
    //       ]
    //     ]
    //   ]
    // ]
    // phpcs:enable

    $properties_def = MapDataDefinition::create();
    $properties_def->setPropertyDefinition('property1', DataDefinition::create('string'));
    $properties_def->setPropertyDefinition('property2', DataDefinition::create('string'));
    $properties_def->setMainPropertyName('property2');

    $values_def = ListDataDefinition::create('map');
    $values_def->setItemDefinition($properties_def);

    $data_item_def = MapDataDefinition::create();
    $data_item_def->setPropertyDefinition('id', DataDefinition::create('string'));
    $data_item_def->setPropertyDefinition('values', $values_def);
    $data_item_def->setMainPropertyName('values');

    $data_def = ListDataDefinition::create('map');
    $data_def->setItemDefinition($data_item_def);

    // Creates an instance of the structure with test source data.
    $target_value = 'target value';
    $source_data = [
      'id' => 'test_id',
      'values' => [
        [
          'property1' => 'wrong value',
          'property2' => $target_value,
        ],
      ],
    ];

    $data = ItemList::createInstance($data_def, 'data');
    $data->appendItem($source_data);

    $extracted_values = $this->fieldsHelper->extractFieldValues($data);
    $this->assertEquals([$target_value], $extracted_values);
  }

  /**
   * Tests that field extraction via the item object also works correctly.
   *
   * @covers \Drupal\search_api\Item\Item::getFields
   */
  public function testItemGetFields(): void {
    $datasource_id = 'entity:entity_test_mulrev_changed';

    // Add two processor-provided fields to the index.
    $field = new Field($this->index, 'value_1');
    $field->setType('string');
    $field->setPropertyPath(TestAddPropertiesProcessor::PROPERTY_NAME);
    $this->index->addField($field);
    $field = new Field($this->index, 'value_2');
    $field->setType('string');
    $field->setDatasourceId($datasource_id);
    $field->setPropertyPath(TestAddPropertiesProcessor::PROPERTY_NAME);
    $this->index->addField($field);

    $entity = $this->entities[0];
    $item = $this->fieldsHelper->createItemFromObject(
      $this->index,
      $entity->getTypedData(),
      Utility::createCombinedId($datasource_id, "{$entity->id()}:en")
    );

    $fields = $item->getFields();

    $this->assertEquals(['Article 1'], $fields['foo']->getValues());
    $this->assertCount(1, $fields['bar']->getValues());
    $this->assertEquals(['foo-article-2'], $fields['value_1']->getValues());
    $this->assertEquals(['foo-article-2'], $fields['value_2']->getValues());
  }

}
