<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\node\Entity\Node;
use Drupal\mukurtu_export\Event\EntityFieldExportEvent;

class CsvExportTimestampFieldTest extends CsvExportFieldTestBase {

  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $node = Node::create([
      'title' => 'Testing Export',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
      'created' => 1682017200,
      'changed' => 1682017200,
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    $this->node = $node;
  }

  /**
   * Test exporting the created field as a human-readable UTC timestamp.
   */
  public function testCreatedFieldExport() {
    $event = new EntityFieldExportEvent('csv', $this->node, 'created', $this->context);
    $this->fieldExporter->exportField($event);
    $this->assertEquals('2023-04-20 19:00:00', $event->getValue()[0]);
  }

  /**
   * Test exporting the changed field as a human-readable UTC timestamp.
   */
  public function testChangedFieldExport() {
    $event = new EntityFieldExportEvent('csv', $this->node, 'changed', $this->context);
    $this->fieldExporter->exportField($event);
    $this->assertEquals('2023-04-20 19:00:00', $event->getValue()[0]);
  }

}
