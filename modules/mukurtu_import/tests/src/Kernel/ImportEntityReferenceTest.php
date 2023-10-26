<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\node\Entity\Node;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;


/**
 * Test the import of entity references.
 */
class ImportEntityReferenceTest extends MukurtuImportTestBase {
  protected $node;
  protected $ref_nodes;


  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_ref',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => [
        'target_type' => 'node',
      ]
    ]);
    $field_storage->save();

    FieldConfig::create([
      'field_name' => 'field_ref',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Keywords',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => [
            'protocol_aware_content' => 'protocol_aware_content',
          ],
          'auto_create' => FALSE,
        ],
      ],
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

    foreach (range(0, 2) as $i) {
      $node = Node::create([
        'title' => "Ref $i",
        'type' => 'protocol_aware_content',
        'status' => TRUE,
        'uid' => $this->currentUser->id(),
      ]);
      $node->setSharingSetting('any');
      $node->setProtocols([$this->protocol]);
      $node->save();
      $this->ref_nodes[$i] = $node;
    }
  }

  /**
   * Testing importing entity references (nodes).
   */
  public function testEntityReferenceImport() {
    $data = [
      ['nid', 'Refs'],
      [$this->node->id(), "Ref 2;{$this->ref_nodes[0]->id()};{$this->ref_nodes[1]->uuid()}"],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_ref', 'source' => 'Refs'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);

    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());

    $refs = $updated_node->get('field_ref')->referencedEntities();
    $this->assertCount(3, $refs);

    // Testing ordering and existence.
    $this->assertEquals($this->ref_nodes[2]->id(), $refs[0]->id());
    $this->assertEquals($this->ref_nodes[0]->id(), $refs[1]->id());
    $this->assertEquals($this->ref_nodes[1]->id(), $refs[2]->id());
  }

}
