<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\node\Entity\Node;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Test the import of username fields.
 */
class ImportUsernameTest extends MukurtuImportTestBase {
  protected $node;
  protected $otherUser;

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

    $user = $this->createUser();
    $user->save();
    $this->community->addMember($user);
    $this->protocol->addMember($user, ['protocol_steward']);
    $this->otherUser = $user;
  }

  /**
   * Test changing the authored by field by importing a username.
   */
  public function testAuthoredByUsernameImport() {
    $this->assertEquals($this->node->getOwnerId(), $this->currentUser->id());

    $data = [
      ['nid', 'Author'],
      [$this->node->id(), $this->otherUser->getAccountName()],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'uid', 'source' => 'Author'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $this->assertEquals($updated_node->getOwnerId(), $this->otherUser->id());
  }

  /**
   * Test changing the authored by field by importing a uid.
   */
  public function testAuthoredByUidImport() {
    $this->assertEquals($this->node->getOwnerId(), $this->currentUser->id());

    $data = [
      ['nid', 'Author'],
      [$this->node->id(), $this->otherUser->id()],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'uid', 'source' => 'Author'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $this->assertEquals($updated_node->getOwnerId(), $this->otherUser->id());
  }

}
