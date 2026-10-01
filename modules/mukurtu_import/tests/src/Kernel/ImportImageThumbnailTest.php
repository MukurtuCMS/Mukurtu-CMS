<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\node\Entity\Node;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test importing image fields split into File ID and alt text columns.
 *
 * Exports write an image field as "<Field> > File ID" and
 * "<Field> > Alternative text" columns, e.g. the "Thumbnail" columns every
 * media bundle exports. A blank File ID cell is what an item without that
 * image exports, so re-importing it unchanged must not fail.
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2305
 */
#[Group('mukurtu_import')]
class ImportImageThumbnailTest extends MukurtuImportTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['image'];

  /**
   * The mapping shipped templates use for an image field's two columns.
   */
  protected const MAPPING = [
    ['target' => 'nid', 'source' => 'ID'],
    ['target' => 'title', 'source' => 'Title'],
    ['target' => 'field_cultural_protocols/protocols', 'source' => 'Protocols'],
    ['target' => 'field_cultural_protocols/sharing_setting', 'source' => 'Sharing Setting'],
    ['target' => 'field_thumbnail/target_id', 'source' => 'Thumbnail > File ID'],
    ['target' => 'field_thumbnail/alt', 'source' => 'Thumbnail > Alternative text'],
  ];

  /**
   * An image file the import can reference by ID.
   *
   * @var \Drupal\file\FileInterface
   */
  protected $imageFile;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['image']);

    FieldStorageConfig::create([
      'field_name' => 'field_thumbnail',
      'entity_type' => 'node',
      'type' => 'image',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_thumbnail',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Thumbnail',
    ])->save();

    // A 1x1 transparent PNG.
    file_put_contents('public://thumbnail.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    $this->imageFile = File::create([
      'uri' => 'public://thumbnail.png',
      'filename' => 'thumbnail.png',
      'uid' => $this->currentUser->id(),
      'status' => 1,
    ]);
    $this->imageFile->save();
  }

  /**
   * Re-importing an item without a thumbnail, with the columns exactly as
   * exported, updates the item instead of failing the row.
   */
  public function testBlankThumbnailOnUpdate(): void {
    $node = $this->createNode('No Thumbnail');

    $import_file = $this->createCsvFile([
      ['ID', 'Title', 'Protocols', 'Sharing Setting', 'Thumbnail > File ID', 'Thumbnail > Alternative text'],
      [$node->id(), 'No Thumbnail Renamed', (string) $this->protocol->id(), 'any', '', ''],
    ]);

    $result = $this->importCsvFile($import_file, self::MAPPING);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $updated = $this->entityTypeManager->getStorage('node')->loadUnchanged($node->id());
    $this->assertEquals('No Thumbnail Renamed', $updated->getTitle());
    $this->assertTrue($updated->get('field_thumbnail')->isEmpty());
  }

  /**
   * A new item with a blank thumbnail cell imports without a thumbnail.
   */
  public function testBlankThumbnailOnNewItem(): void {
    $import_file = $this->createCsvFile([
      ['ID', 'Title', 'Protocols', 'Sharing Setting', 'Thumbnail > File ID', 'Thumbnail > Alternative text'],
      ['', 'New Without Thumbnail', (string) $this->protocol->id(), 'any', '', ''],
    ]);

    $result = $this->importCsvFile($import_file, self::MAPPING);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties(['title' => 'New Without Thumbnail']);
    $this->assertCount(1, $nodes);
    $this->assertTrue(reset($nodes)->get('field_thumbnail')->isEmpty());
  }

  /**
   * An existing file ID still sets the image and its alt text.
   */
  public function testThumbnailFileIdStillApplies(): void {
    $node = $this->createNode('Gets A Thumbnail');

    $import_file = $this->createCsvFile([
      ['ID', 'Title', 'Protocols', 'Sharing Setting', 'Thumbnail > File ID', 'Thumbnail > Alternative text'],
      [$node->id(), 'Gets A Thumbnail', (string) $this->protocol->id(), 'any', $this->imageFile->id(), 'A small square'],
    ]);

    $result = $this->importCsvFile($import_file, self::MAPPING);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $updated = $this->entityTypeManager->getStorage('node')->loadUnchanged($node->id());
    $this->assertEquals($this->imageFile->id(), $updated->get('field_thumbnail')->target_id);
    $this->assertEquals('A small square', $updated->get('field_thumbnail')->alt);
  }

  /**
   * A value that matches no file fails the row with a readable message.
   */
  public function testUnmatchedThumbnailFailsWithMessage(): void {
    $node = $this->createNode('Typo Thumbnail');

    $import_file = $this->createCsvFile([
      ['ID', 'Title', 'Protocols', 'Sharing Setting', 'Thumbnail > File ID', 'Thumbnail > Alternative text'],
      [$node->id(), 'Typo Thumbnail Renamed', (string) $this->protocol->id(), 'any', 'nope.jpg', 'Alt'],
    ]);

    $this->importCsvFile($import_file, self::MAPPING);

    $updated = $this->entityTypeManager->getStorage('node')->loadUnchanged($node->id());
    $this->assertEquals('Typo Thumbnail', $updated->getTitle(), 'A row with an unmatched image must not be imported.');

    $messages = iterator_to_array($this->lastMigration->getIdMap()->getMessages());
    $this->assertCount(1, $messages);
    $this->assertStringContainsString('No image file matches "nope.jpg"', $messages[0]->message);
  }

  /**
   * Creates a protocol-aware node without a thumbnail.
   */
  protected function createNode(string $title): Node {
    $node = Node::create([
      'title' => $title,
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    return $node;
  }

}
