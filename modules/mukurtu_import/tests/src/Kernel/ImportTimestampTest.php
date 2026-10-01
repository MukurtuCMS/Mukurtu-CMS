<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\node\Entity\Node;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Test the import of timestamp fields.
 */
class ImportTimestampTest extends MukurtuImportTestBase {
  protected $node;

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
  }

  /**
   * Test importing a timestamp.
   */
  public function testTimestamp() {
    $new_created_time = '1682017200';
    $new_created_time_human_readable = '2023-04-20 19:00:00';
    $data = [
      ['nid', 'created'],
      [$this->node->id(), $new_created_time_human_readable],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'created', 'source' => 'created'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $this->assertEquals($new_created_time, $updated_node->getCreatedTime());
  }

  /**
   * A blank "created" cell on a new entity falls back to the request time
   * instead of failing validation.
   *
   * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2305
   */
  public function testBlankCreatedOnNewEntityUsesRequestTime() {
    $data = [
      ['Title', 'Protocols', 'Sharing Setting', 'Authored on'],
      ['Blank Authored On', (string) $this->protocol->id(), 'any', ''],
    ];
    $import_file = $this->createCsvFile($data);

    $before = \Drupal::time()->getRequestTime();
    $result = $this->importCsvFile($import_file, $this->newNodeMapping());
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties(['title' => 'Blank Authored On']);
    $this->assertCount(1, $nodes);
    $this->assertGreaterThanOrEqual($before, reset($nodes)->getCreatedTime());
  }

  /**
   * A mapped "created" column that is absent from the file is ignored, as
   * when a shipped *_all_fields template is used with a trimmed-down CSV.
   *
   * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2305
   */
  public function testAbsentCreatedColumnOnNewEntity() {
    $data = [
      ['Title', 'Protocols', 'Sharing Setting'],
      ['Absent Authored On', (string) $this->protocol->id(), 'any'],
    ];
    $import_file = $this->createCsvFile($data);

    $before = \Drupal::time()->getRequestTime();
    $result = $this->importCsvFile($import_file, $this->newNodeMapping());
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties(['title' => 'Absent Authored On']);
    $this->assertCount(1, $nodes);
    $this->assertGreaterThanOrEqual($before, reset($nodes)->getCreatedTime());
  }

  /**
   * A blank "created" cell on an update keeps the existing value rather than
   * clearing it.
   *
   * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2305
   */
  public function testBlankCreatedOnUpdateKeepsExistingValue() {
    $this->node->setCreatedTime(1682017200)->save();

    $data = [
      ['nid', 'title', 'created'],
      [$this->node->id(), 'Updated With Blank Created', ''],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'title', 'source' => 'title'],
      ['target' => 'created', 'source' => 'created'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node = $this->entityTypeManager->getStorage('node')->loadUnchanged($this->node->id());
    $this->assertEquals('Updated With Blank Created', $updated_node->getTitle());
    $this->assertEquals(1682017200, $updated_node->getCreatedTime());
  }

  /**
   * The new-content mapping used by shipped templates, including "created".
   */
  protected function newNodeMapping(): array {
    return [
      ['target' => 'title', 'source' => 'Title'],
      ['target' => 'field_cultural_protocols/protocols', 'source' => 'Protocols'],
      ['target' => 'field_cultural_protocols/sharing_setting', 'source' => 'Sharing Setting'],
      ['target' => 'created', 'source' => 'Authored on'],
    ];
  }

  /**
   * Test that importing an update to an existing node bumps "changed".
   *
   * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1574
   */
  public function testChangedTimeAdvancesOnUpdate() {
    $original_changed_time = $this->node->getChangedTime();

    // Guarantee the next request time differs from the original "changed"
    // value so the assertion below is meaningful.
    sleep(1);

    $data = [
      ['nid', 'title'],
      [$this->node->id(), 'Updated via spreadsheet'],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'title', 'source' => 'title'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $this->assertEquals('Updated via spreadsheet', $updated_node->getTitle());
    $this->assertGreaterThan($original_changed_time, $updated_node->getChangedTime());
  }

}
