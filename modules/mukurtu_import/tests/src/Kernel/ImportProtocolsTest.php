<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\node\Entity\Node;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\mukurtu_protocol\Entity\Protocol;

/**
 * Test the import of protocol fields.
 */
class ImportProtocolsTest extends MukurtuImportTestBase {
  protected $node;
  protected $protocol2;
  protected $protocol3;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $node = Node::create([
      'title' => 'Boolean Test',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    $this->node = $node;

    $protocol = Protocol::create([
      'name' => "Protocol 2",
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'open',
    ]);
    $protocol->save();
    $protocol->addMember($this->currentUser, ['protocol_steward']);
    $this->protocol2 = $protocol;

    $protocol = Protocol::create([
      'name' => "Protocol 3",
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'strict',
    ]);
    $protocol->save();
    $protocol->addMember($this->currentUser, ['protocol_steward']);
    $this->protocol3 = $protocol;
  }

  /**
   * Test importing a sharing setting.
   */
  public function testSharingSettingOnly() {
    $data = [
      ['nid', 'Sharing Setting'],
      [$this->node->id(), 'all'],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_cultural_protocols/sharing_setting', 'source' => 'Sharing Setting'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $this->assertEquals('all', $updated_node->getSharingSetting());
  }

  /**
   * Test importing protocols by ID.
   */
  public function testProtocolOnlyById() {
    $data = [
      ['nid', 'Protocols'],
      [$this->node->id(), "{$this->protocol2->id()};{$this->protocol3->id()}"],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_cultural_protocols/protocols', 'source' => 'Protocols'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $this->assertEquals([$this->protocol2->id(), $this->protocol3->id()], $updated_node->getProtocols());
  }

  /**
   * Test importing protocols by UUID.
   */
  public function testProtocolOnlyByUUID() {
    $data = [
      ['nid', 'Protocols'],
      [$this->node->id(), "{$this->protocol2->uuid()};{$this->protocol3->uuid()}"],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_cultural_protocols/protocols', 'source' => 'Protocols'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $this->assertEquals([$this->protocol2->id(), $this->protocol3->id()], $updated_node->getProtocols());
  }

  /**
   * Test importing protocols, by name.
   */
  public function testProtocolOnlyByName() {
    $data = [
      ['nid', 'Protocols'],
      [$this->node->id(), "{$this->protocol2->getName()};{$this->protocol3->getName()}"],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_cultural_protocols/protocols', 'source' => 'Protocols'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $this->assertEquals([$this->protocol2->id(), $this->protocol3->id()], $updated_node->getProtocols());
  }

  /**
   * Test importing a protocol string.
   */
  public function testProtocolString() {
    $data = [
      ['nid', 'Protocols'],
      [$this->node->id(), "Any({$this->protocol->id()},{$this->protocol2->id()},{$this->protocol3->id()})"],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_cultural_protocols', 'source' => 'Protocols'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $this->assertEquals([$this->protocol->id(), $this->protocol2->id(), $this->protocol3->id()], $updated_node->getProtocols());
  }

}
