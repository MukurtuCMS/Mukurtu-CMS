<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\Media;
use Drupal\mukurtu_import\BulkEdit\GridCellText;
use Drupal\node\Entity\Node;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the text the per-item grid shows in a cell.
 */
#[Group('mukurtu_import')]
class GridCellTextTest extends MukurtuImportTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['media', 'image', 'mukurtu_export', 'mukurtu_multipage_items'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('media');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['media', 'image', 'file']);
    $this->installEntitySchema('multipage_item');
    $this->createMediaType('image', ['id' => 'photo']);

    FieldStorageConfig::create([
      'field_name' => 'field_media_assets',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => ['target_type' => 'media'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_media_assets',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Media Assets',
    ])->save();
  }

  /**
   * Media references show as IDs, separated by semicolons.
   */
  public function testMediaReferences(): void {
    $ids = [];
    foreach (['Report', 'Letter'] as $name) {
      $media = Media::create(['bundle' => 'photo', 'name' => $name, 'uid' => $this->currentUser->id()]);
      $media->save();
      $ids[] = $media->id();
    }
    $node = Node::create([
      'title' => 'Canoe',
      'type' => 'protocol_aware_content',
      'uid' => $this->currentUser->id(),
      'field_media_assets' => $ids,
    ]);

    $this->assertSame(implode(';', $ids), (new GridCellText())->text($node, 'field_media_assets'));
  }

}
