<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests importing alt text onto the image of a referenced media item.
 *
 * Templates map columns such as "Collection image > Alternative text" to
 * "field_collection_image/alt", where field_collection_image references a
 * media item. The destination writes that alt text to the media item's
 * image field.
 *
 * @see \Drupal\mukurtu_import\Plugin\migrate\destination\ProtocolAwareEntityContent::resolveMediaAltTextChanges()
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2309
 */
#[Group('mukurtu_import')]
class ImportMediaAltTextTest extends MukurtuImportTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['media', 'image'];

  /**
   * The mapping used by every test.
   */
  protected const MAPPING = [
    ['target' => 'nid', 'source' => 'ID'],
    ['target' => 'title', 'source' => 'Title'],
    ['target' => 'field_test_media/target_id', 'source' => 'Media'],
    ['target' => 'field_test_media/alt', 'source' => 'Media > Alternative text'],
  ];

  /**
   * The referenced image media item.
   */
  protected MediaInterface $media;

  /**
   * The media item's image field name.
   */
  protected string $sourceField;

  /**
   * A node referencing the media item.
   */
  protected NodeInterface $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('media');
    $this->installConfig(['media', 'image', 'file']);

    $media_type = $this->createMediaType('image');
    $source_field = $media_type->getSource()->getSourceFieldDefinition($media_type)->getName();

    FieldStorageConfig::create([
      'field_name' => 'field_test_media',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => 1,
      'settings' => ['target_type' => 'media'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_test_media',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Media',
    ])->save();

    // A 1x1 transparent PNG.
    file_put_contents('public://alt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    $file = File::create([
      'uri' => 'public://alt.png',
      'filename' => 'alt.png',
      'uid' => $this->currentUser->id(),
      'status' => 1,
    ]);
    $file->save();

    $this->media = Media::create([
      'bundle' => $media_type->id(),
      'name' => 'Pictured',
      $source_field => ['target_id' => $file->id(), 'alt' => 'Original alt'],
      'uid' => $this->currentUser->id(),
    ]);
    $this->media->save();
    $this->sourceField = $source_field;

    $this->node = Node::create([
      'title' => 'Has Media',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
      'field_test_media' => ['target_id' => $this->media->id()],
    ]);
    $this->node->setSharingSetting('any');
    $this->node->setProtocols([$this->protocol]);
    $this->node->save();
  }

  /**
   * A new alt text is written to the media item, and the row is Updated.
   */
  public function testAltTextIsApplied(): void {
    $result = $this->importRow('Has Media', 'New alt');

    $this->assertSame('updated', $result['outcome']);
    $this->assertSame('New alt', $this->reloadMedia()->get($this->sourceField)->alt);
  }

  /**
   * The same alt text leaves the media item alone, and the row is Unchanged.
   */
  public function testSameAltTextIsUnchanged(): void {
    $revision_before = $this->media->getRevisionId();

    $result = $this->importRow('Has Media', 'Original alt');

    $this->assertSame('unchanged', $result['outcome']);
    $this->assertSame($revision_before, $this->reloadMedia()->getRevisionId(), 'An unchanged alt text must not re-save the media item.');
  }

  /**
   * A changed alt text alone makes an otherwise identical row Updated.
   */
  public function testAltTextChangeAloneIsUpdated(): void {
    $result = $this->importRow('Has Media', 'Only the alt changed');

    $this->assertSame('updated', $result['outcome']);
  }

  /**
   * An importer who can create content but can't edit the media item it
   * references can't change that media item's alt text, and nothing in the
   * row is saved.
   */
  public function testAltTextOnMediaWithoutAccessFailsRow(): void {
    // Can see and reference the media item, but not edit it.
    $outsider = $this->createUser([], ['view media']);
    $this->community->addMember($outsider);
    $this->protocol->addMember($outsider, ['protocol_steward']);
    $this->setCurrentUser($outsider);

    $import_file = $this->createCsvFile([
      ['Title', 'Protocols', 'Sharing Setting', 'Media', 'Media > Alternative text'],
      ['Outsider Item', (string) $this->protocol->id(), 'any', $this->media->id(), 'Outsider alt'],
    ]);
    $strategy = MukurtuImportStrategy::create(['uid' => $outsider->id()]);
    $strategy->setTargetEntityTypeId('node');
    $strategy->setTargetBundle('protocol_aware_content');
    $strategy->setMapping([
      ['target' => 'title', 'source' => 'Title'],
      ['target' => 'field_cultural_protocols/protocols', 'source' => 'Protocols'],
      ['target' => 'field_cultural_protocols/sharing_setting', 'source' => 'Sharing Setting'],
      ['target' => 'field_test_media/target_id', 'source' => 'Media'],
      ['target' => 'field_test_media/alt', 'source' => 'Media > Alternative text'],
    ]);
    $definition = $strategy->toDefinition($import_file);
    // Mukurtu limits which media a non-admin can reference to those their
    // protocol grants cover (mukurtu_protocol_query_media_access_alter()),
    // and this test's plain media type has no protocols, so reference
    // validation would reject the row first. Turning validation off here
    // isolates the media update access check this test is about.
    $definition['destination']['validate'] = FALSE;
    $this->lastMigration = \Drupal::service('plugin.manager.migration')->createStubMigration($definition);
    (new MigrateExecutable($this->lastMigration, new MigrateMessage()))->import();

    $this->assertSame('Original alt', $this->reloadMedia()->get($this->sourceField)->alt);
    $this->assertEmpty($this->entityTypeManager->getStorage('node')->loadByProperties(['title' => 'Outsider Item']), 'A row that fails on media access must not create its item.');

    $messages = iterator_to_array($this->lastMigration->getIdMap()->getMessages());
    $this->assertCount(1, $messages);
    $this->assertStringContainsString(sprintf("does not have update access for media item %s, so its alternative text can't be changed.", $this->media->id()), $messages[0]->message);
  }

  /**
   * Imports one row for the node and returns its recorded result.
   */
  protected function importRow(string $title, string $alt): array {
    $import_file = $this->createCsvFile([
      ['ID', 'Title', 'Media', 'Media > Alternative text'],
      [$this->node->id(), $title, $this->media->id(), $alt],
    ]);
    $result = $this->importCsvFile($import_file, self::MAPPING);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $row_results = $this->lastMigration->getDestinationPlugin()->getAndClearRowResults();
    $this->assertCount(1, $row_results);
    return $row_results[0];
  }

  /**
   * Reloads the media item from storage.
   */
  protected function reloadMedia(): MediaInterface {
    return $this->entityTypeManager->getStorage('media')->loadUnchanged($this->media->id());
  }

}
