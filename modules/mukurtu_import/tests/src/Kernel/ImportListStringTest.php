<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\node\Entity\Node;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;


/**
 * Test the import of list string fields.
 */
class ImportListStringTest extends MukurtuImportTestBase {
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_list',
      'entity_type' => 'node',
      'type' => 'list_string',
      'cardinality' => -1,
      'settings' => [
        'allowed_values' => [
          'http://creativecommons.org/licenses/by/4.0' => t('Attribution 4.0 International (CC BY 4.0)'),
          'http://creativecommons.org/licenses/by-nc/4.0' => t('Attribution-NonCommercial 4.0 International (CC BY-NC 4.0)'),
          'http://creativecommons.org/licenses/by-sa/4.0' => t('Attribution-ShareAlike 4.0 International (CC BY-SA 4.0)'),
          'http://creativecommons.org/licenses/by-nc-sa/4.0' => t('Attribution-NonCommercial-ShareAlike 4.0 International (CC BY-NC-SA 4.0)'),
          'http://creativecommons.org/licenses/by-nd/4.0' => t('Attribution-NoDerivatives 4.0 International (CC BY-ND 4.0)'),
          'http://creativecommons.org/licenses/by-nc-nd/4.0' => t('Attribution-NonCommercial-NoDerivatives 4.0 International (CC BY-NC-ND 4.0)'),
        ],
      ],
    ]);
    $field_storage->save();

    FieldConfig::create([
      'field_name' => 'field_list',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'List',
    ])->save();

    $node = Node::create([
      'title' => 'Title',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    $this->node = $node;
  }

  /**
   * Test importing a single list item by key.
   */
  public function testSingleListImportByKey() {
    $data = [
      ['nid', 'List'],
      [$this->node->id(), "http://creativecommons.org/licenses/by-nc-sa/4.0"],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_list', 'source' => 'List'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $list = $updated_node->get('field_list')->getValue();
    $this->assertEquals("http://creativecommons.org/licenses/by-nc-sa/4.0", $list[0]['value']);
  }

  /**
   * Test importing a single list item by value.
   */
  public function testSingleListImportByValue() {
    $data = [
      ['nid', 'List'],
      [$this->node->id(), "Attribution-NoDerivatives 4.0 International (CC BY-ND 4.0)"],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_list', 'source' => 'List'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $list = $updated_node->get('field_list')->getValue();
    $this->assertEquals("http://creativecommons.org/licenses/by-nd/4.0", $list[0]['value']);
  }

  /**
   * Test importing multiple list items by key and value.
   */
  public function testMultipleListImports() {
    $data = [
      ['nid', 'List'],
      [$this->node->id(), "Attribution-NoDerivatives 4.0 International (CC BY-ND 4.0);http://creativecommons.org/licenses/by-nc/4.0;Attribution-NonCommercial-NoDerivatives 4.0 International (CC BY-NC-ND 4.0)"],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_list', 'source' => 'List'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $list = $updated_node->get('field_list')->getValue();
    $this->assertCount(3, $list);
    $this->assertEquals("http://creativecommons.org/licenses/by-nd/4.0", $list[0]['value']);
    $this->assertEquals("http://creativecommons.org/licenses/by-nc/4.0", $list[1]['value']);
    $this->assertEquals("http://creativecommons.org/licenses/by-nc-nd/4.0", $list[2]['value']);
  }

}
