<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\node\Entity\Node;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the per-row outcome the destination records for the results tables.
 *
 * @see \Drupal\mukurtu_import\Plugin\migrate\destination\ProtocolAwareEntityContent::determineOutcome()
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2309
 */
#[Group('mukurtu_import')]
class ImportRowOutcomeTest extends MukurtuImportTestBase {

  /**
   * The mapping used by every test.
   */
  protected const MAPPING = [
    ['target' => 'nid', 'source' => 'ID'],
    ['target' => 'title', 'source' => 'Title'],
    ['target' => 'field_cultural_protocols/protocols', 'source' => 'Protocols'],
    ['target' => 'field_cultural_protocols/sharing_setting', 'source' => 'Sharing Setting'],
  ];

  /**
   * An existing node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->node = Node::create([
      'title' => 'Existing Item',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $this->node->setSharingSetting('any');
    $this->node->setProtocols([$this->protocol]);
    $this->node->save();
  }

  /**
   * A row that creates an item is New, and the existing status is kept.
   */
  public function testNewRow(): void {
    $result = $this->importRow('', 'Brand New Item');

    $this->assertSame('created', $result['status']);
    $this->assertSame('new', $result['outcome']);
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties(['title' => 'Brand New Item']);
    $this->assertSame((string) reset($nodes)->id(), $result['entity_id']);
    $this->assertSame('node', $result['entity_type_id']);
    $this->assertSame(reset($nodes)->language()->getId(), $result['langcode']);
  }

  /**
   * A row whose values all match the stored item is Unchanged, while still
   * counting as an update for the import log.
   */
  public function testIdenticalRowIsUnchanged(): void {
    $result = $this->importRow((string) $this->node->id(), 'Existing Item');

    $this->assertSame('updated', $result['status']);
    $this->assertSame('unchanged', $result['outcome']);
    $this->assertSame((string) $this->node->id(), $result['entity_id']);
  }

  /**
   * A row that changes a value is Updated.
   */
  public function testChangedRowIsUpdated(): void {
    $result = $this->importRow((string) $this->node->id(), 'Existing Item Renamed');

    $this->assertSame('updated', $result['status']);
    $this->assertSame('updated', $result['outcome']);
  }

  /**
   * Imports one row and returns the destination's recorded result for it.
   */
  protected function importRow(string $id, string $title): array {
    $import_file = $this->createCsvFile([
      ['ID', 'Title', 'Protocols', 'Sharing Setting'],
      [$id, $title, (string) $this->protocol->id(), 'any'],
    ]);
    $result = $this->importCsvFile($import_file, self::MAPPING);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $row_results = $this->lastMigration->getDestinationPlugin()->getAndClearRowResults();
    $this->assertCount(1, $row_results);
    return $row_results[0];
  }

}
