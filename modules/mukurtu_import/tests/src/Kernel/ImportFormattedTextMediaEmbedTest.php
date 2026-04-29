<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\filter\Entity\FilterFormat;
use Drupal\media\Entity\Media;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\node\Entity\Node;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Tests that drupal-media embed tags in formatted text fields can reference
 * media assets by name or filename during CSV import.
 *
 * @group mukurtu_import
 */
class ImportFormattedTextMediaEmbedTest extends MukurtuImportTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['media', 'image'];

  /**
   * A node to update during import.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * A media entity for name-based lookup tests.
   *
   * @var \Drupal\media\Entity\Media
   */
  protected $mediaByName;

  /**
   * A media entity for filename-based lookup tests.
   *
   * @var \Drupal\media\Entity\Media
   */
  protected $mediaByFilename;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('media');
    $this->installConfig(['media', 'image', 'file']);

    // Create the basic_html text format expected by FormattedTextProcessCallback.
    FilterFormat::create([
      'format' => 'basic_html',
      'name' => 'Basic HTML',
    ])->save();

    // Create a generic file media type.
    $media_type = $this->createMediaType('file');
    $source_field = $media_type->getSource()->getSourceFieldDefinition($media_type)->getName();

    // Add a text_long body field to the node type.
    FieldStorageConfig::create([
      'field_name' => 'field_body',
      'entity_type' => 'node',
      'type' => 'text_long',
      'cardinality' => 1,
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_body',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Body',
    ])->save();

    // Create a node to update via import.
    $node = Node::create([
      'title' => 'Test Node',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    $this->node = $node;

    // Create a file + media entity for name-based lookup.
    $file_name = File::create([
      'uri' => 'public://name-lookup.txt',
      'filename' => 'name-lookup.txt',
      'uid' => $this->currentUser->id(),
      'status' => 1,
    ]);
    $file_name->save();

    $this->mediaByName = Media::create([
      'bundle' => $media_type->id(),
      'name' => 'My Named Asset',
      $source_field => ['target_id' => $file_name->id()],
      'uid' => $this->currentUser->id(),
    ]);
    $this->mediaByName->save();

    // Create a file + media entity for filename-based lookup.
    $file_fn = File::create([
      'uri' => 'public://filename-lookup.txt',
      'filename' => 'filename-lookup.txt',
      'uid' => $this->currentUser->id(),
      'status' => 1,
    ]);
    $file_fn->save();

    $this->mediaByFilename = Media::create([
      'bundle' => $media_type->id(),
      'name' => 'Asset Looked Up By Filename',
      $source_field => ['target_id' => $file_fn->id()],
      'uid' => $this->currentUser->id(),
    ]);
    $this->mediaByFilename->save();
  }

  /**
   * Tests that data-entity-name resolves to the correct UUID.
   */
  public function testResolveByName(): void {
    $embed = '<drupal-media data-entity-type="media" data-entity-name="My Named Asset" data-view-mode="default">&nbsp;</drupal-media>';
    $data = [
      ['nid', 'body'],
      [$this->node->id(), $embed],
    ];
    $import_file = $this->createCsvFile($data);
    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_body', 'source' => 'body'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $body = $updated_node->get('field_body')->value;

    $this->assertStringContainsString('data-entity-uuid="' . $this->mediaByName->uuid() . '"', $body);
    $this->assertStringNotContainsString('data-entity-name=', $body);
  }

  /**
   * Tests that data-entity-filename resolves to the correct UUID.
   */
  public function testResolveByFilename(): void {
    $embed = '<drupal-media data-entity-type="media" data-entity-filename="filename-lookup.txt" data-view-mode="default">&nbsp;</drupal-media>';
    $data = [
      ['nid', 'body'],
      [$this->node->id(), $embed],
    ];
    $import_file = $this->createCsvFile($data);
    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_body', 'source' => 'body'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $body = $updated_node->get('field_body')->value;

    $this->assertStringContainsString('data-entity-uuid="' . $this->mediaByFilename->uuid() . '"', $body);
    $this->assertStringNotContainsString('data-entity-filename=', $body);
  }

  /**
   * Tests that existing data-entity-uuid tags pass through unchanged.
   */
  public function testExistingUuidPassesThrough(): void {
    $uuid = $this->mediaByName->uuid();
    $embed = '<drupal-media data-entity-type="media" data-entity-uuid="' . $uuid . '" data-view-mode="default">&nbsp;</drupal-media>';
    $data = [
      ['nid', 'body'],
      [$this->node->id(), $embed],
    ];
    $import_file = $this->createCsvFile($data);
    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_body', 'source' => 'body'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $body = $updated_node->get('field_body')->value;

    $this->assertStringContainsString('data-entity-uuid="' . $uuid . '"', $body);
  }

  /**
   * Tests that an unknown name leaves the tag unchanged and migration completes.
   */
  public function testUnknownNameLeavesTagUnchanged(): void {
    $embed = '<drupal-media data-entity-type="media" data-entity-name="Does Not Exist" data-view-mode="default">&nbsp;</drupal-media>';
    $data = [
      ['nid', 'body'],
      [$this->node->id(), $embed],
    ];
    $import_file = $this->createCsvFile($data);
    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_body', 'source' => 'body'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $body = $updated_node->get('field_body')->value;

    $this->assertStringContainsString('data-entity-name="Does Not Exist"', $body);
    $this->assertStringNotContainsString('data-entity-uuid=', $body);
  }

}
