<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\node\Entity\Node;
use Drupal\mukurtu_export\Event\EntityFieldExportEvent;

class CsvExportUsername extends CsvExportFieldTestBase {

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
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    $this->node = $node;
    $this->event = new EntityFieldExportEvent('csv', $this->node, 'uid', $this->context);
  }

  /**
   * Test exporting a username as an ID.
   */
  public function testUserIdExport() {
    $this->fieldExporter->exportField($this->event);
    $this->assertEquals($this->currentUser->id(), $this->event->getValue()[0]);
  }

  /**
   * Test exporting a multiple value text field.
   */
  public function testUsernameExport() {
    $this->export_config->setEntityReferenceSetting('user', 'username');
    $this->export_config->save();

    $this->fieldExporter->exportField($this->event);
    $this->assertEquals($this->currentUser->getAccountName(), $this->event->getValue()[0]);
  }

}
