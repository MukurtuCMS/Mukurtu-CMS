<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\node\Entity\Node;
use Drupal\mukurtu_export\Event\EntityFieldExportEvent;
use Drupal\mukurtu_protocol\Entity\Protocol;

class CsvExportProtocolFieldTest extends CsvExportFieldTestBase {

  protected $node;
  protected $protocol2;
  protected $protocol3;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $protocol2 = Protocol::create([
      'name' => "Protocol 2",
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'open',
    ]);
    $protocol2->save();
    $protocol2->addMember($this->currentUser, ['protocol_steward']);
    $this->protocol2 = $protocol2;

    $protocol3 = Protocol::create([
      'name' => "Protocol 3",
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'open',
    ]);
    $protocol3->save();
    $protocol3->addMember($this->currentUser, ['protocol_steward']);
    $this->protocol3 = $protocol3;

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

    $this->event = new EntityFieldExportEvent('csv', $this->node, 'field_cultural_protocols', $this->context);
  }

  /**
   * Test exporting a protocol field.
   */
  public function testProtocolFieldExport() {
    $this->fieldExporter->exportField($this->event);
    $protocolString = $this->node->getSharingSetting() . "(" . implode(',', $this->node->getProtocols()) . ")";
    $this->assertCount(1, $this->event->getValue());
    $this->assertEquals($protocolString, $this->event->getValue()[0]);

    // Switch to all, multiple protocols.
    $this->node->setSharingSetting('all');
    $this->node->setProtocols([$this->protocol, $this->protocol2, $this->protocol3]);
    $this->node->save();
    $this->fieldExporter->exportField($this->event);
    $protocolString = $this->node->getSharingSetting() . "(" . implode(',', $this->node->getProtocols()) . ")";
    $this->assertCount(1, $this->event->getValue());
    $this->assertEquals($protocolString, $this->event->getValue()[0]);
  }

  /**
   * Test exporting a protocol field as UUIDs.
   */
  public function testProtocolFieldUuidExport() {
    $this->export_config->setIdFieldSetting('uuid');
    $this->export_config->save();

    $this->fieldExporter->exportField($this->event);
    $protocolString = $this->node->getSharingSetting() . "(" . implode(',', array_map(fn($p) => $p->uuid(), $this->node->getProtocolEntities())) . ")";
    $this->assertCount(1, $this->event->getValue());
    $this->assertEquals($protocolString, $this->event->getValue()[0]);

    // Switch to all, multiple protocols.
    $this->node->setSharingSetting('all');
    $this->node->setProtocols([$this->protocol, $this->protocol2, $this->protocol3]);
    $this->node->save();
    $this->fieldExporter->exportField($this->event);
    $protocolString = $this->node->getSharingSetting() . "(" . implode(',', array_map(fn($p) => $p->uuid(), $this->node->getProtocolEntities())) . ")";
    $this->assertCount(1, $this->event->getValue());
    $this->assertEquals($protocolString, $this->event->getValue()[0]);
  }

}
