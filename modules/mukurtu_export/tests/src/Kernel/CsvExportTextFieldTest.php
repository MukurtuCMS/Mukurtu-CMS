<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\node\Entity\Node;
use Drupal\mukurtu_export\Event\EntityFieldExportEvent;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

class CsvExportTextFieldTest extends CsvExportFieldTestBase {

  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_text_multiple',
      'entity_type' => 'node',
      'type' => 'string',
      'cardinality' => -1,
    ]);
    $field_storage->save();

    FieldConfig::create([
      'field_name' => 'field_text_multiple',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Text',
    ])->save();

    $node = Node::create([
      'title' => 'Testing Export',
      'type' => 'protocol_aware_content',
      'field_text_multiple' => ['String 1', 'String 2'],
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    $this->node = $node;
    $this->event = new EntityFieldExportEvent('csv', $this->node, 'title', $this->context);
  }

  /**
   * Test exporting a single value text field.
   */
  public function testSingleTextFieldExport() {
    $this->fieldExporter->exportField($this->event);
    $this->assertEquals($this->node->getTitle(), $this->event->getValue()[0]);
  }

  /**
   * Test exporting a multiple value text field.
   */
  public function testMultipleTextFieldExport() {
    $event = new EntityFieldExportEvent('csv', $this->node, 'field_text_multiple', $this->context);
    $this->fieldExporter->exportField($event);
    $this->assertCount(2, $event->getValue());
    $this->assertEquals('String 1', $event->getValue()[0]);
    $this->assertEquals('String 2', $event->getValue()[1]);
  }

}
