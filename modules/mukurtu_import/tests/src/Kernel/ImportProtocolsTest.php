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

  /**
   * Test that update access is checked against the node's current protocols,
   * not the protocols the import row is trying to set.
   *
   * Regression test for ProtocolAwareEntityContent::updateEntity(): the
   * access check there must run against the original, unmodified entity
   * before the row's field values are applied. Otherwise an importer with no
   * update access to a node under its current (restricted) protocol could
   * grant themselves access simply by re-pointing the node at a different
   * protocol they do belong to, in the very same row that makes other edits.
   */
  public function testCannotBypassAccessByChangingProtocol() {
    $restricted = Protocol::create([
      'name' => 'Restricted Protocol',
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'strict',
    ]);
    $restricted->save();
    $restricted->addMember($this->currentUser, ['protocol_steward']);

    $node = Node::create([
      'title' => 'Restricted Node',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$restricted]);
    $node->save();

    // A protocol the outsider does belong to, as an accessible target for
    // the bypass attempt.
    $accessible = Protocol::create([
      'name' => 'Accessible Protocol',
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'strict',
    ]);
    $accessible->save();

    $outsider = $this->createUser();
    $this->community->addMember($outsider);
    $accessible->addMember($outsider, ['protocol_steward']);
    $this->setCurrentUser($outsider);

    $data = [
      ['nid', 'Title', 'Protocols'],
      [$node->id(), 'Hijacked Title', $accessible->id()],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'title', 'source' => 'Title'],
      ['target' => 'field_cultural_protocols/protocols', 'source' => 'Protocols'],
    ];

    $this->importCsvFile($import_file, $mapping);

    $updated_node = $this->entityTypeManager->getStorage('node')->load($node->id());
    $this->assertEquals('Restricted Node', $updated_node->getTitle(), 'A user without update access to the node under its current protocol must not be able to update it by re-pointing it at a protocol they belong to in the same import row.');
    $this->assertEquals([$restricted->id()], $updated_node->getProtocols());

    $messages = iterator_to_array($this->lastMigration->getIdMap()->getMessages());
    $this->assertNotEmpty($messages);
    $this->assertStringContainsString('does not have access to update', reset($messages)->message);
  }

  /**
   * The exported CSV header labels must match what auto-mapping expects,
   * so a re-imported export doesn't require manual column mapping (#2029).
   */
  public function testExportedHeaderMatchesAutoMapLabel() {
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'protocol_aware_content');
    $field_definition = $field_definitions['field_cultural_protocols'];

    $plugin_manager = \Drupal::service('plugin.manager.mukurtu_import_field_process');
    $plugin = $plugin_manager->createInstance('cultural_protocol');
    $supported_properties = $plugin->getSupportedProperties($field_definition);

    $this->assertEquals('Cultural Protocols > Protocols', $supported_properties['protocols']['label']);
    $this->assertEquals('Cultural Protocols > Sharing Setting', $supported_properties['sharing_setting']['label']);
  }

}
