<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\mukurtu_import\BulkEdit\GridColumns;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests which fields the per-item grid offers.
 */
#[Group('mukurtu_import')]
class GridColumnsTest extends MukurtuImportTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['media', 'image', 'media_test_source'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('media');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['media', 'image', 'file']);
  }

  /**
   * Returns the grid columns for content or media.
   */
  protected function columns(string $view_id): GridColumns {
    return new GridColumns(_mukurtu_core_bulk_edit_columns($view_id));
  }

  /**
   * Media offers a text source and Published; a file source is left out.
   */
  public function testMedia(): void {
    $text = $this->createMediaType('test', ['id' => 'embed']);
    $file = $this->createMediaType('file', ['id' => 'document']);
    $columns = $this->columns('mukurtu_bulk_edit_media');

    $text_source = $text->getSource()->getSourceFieldDefinition($text)->getName();
    $available = $columns->available('embed');
    $this->assertSame(['name', $text_source], array_slice(array_keys($available), 0, 2));
    $this->assertArrayHasKey('status', $available);

    $file_source = $file->getSource()->getSourceFieldDefinition($file)->getName();
    $this->assertArrayNotHasKey($file_source, $columns->available('document'));
  }

  /**
   * Content starts with its title and doesn't offer Published.
   */
  public function testContent(): void {
    $available = $this->columns('mukurtu_bulk_edit_content')->available('protocol_aware_content');
    $this->assertSame('title', array_key_first($available));
    $this->assertArrayNotHasKey('status', $available);
    $this->assertArrayNotHasKey('moderation_state', $available);
  }

  /**
   * A saved choice keeps only fields the grid offers, with the label first.
   */
  public function testSave(): void {
    $this->createMediaType('test', ['id' => 'embed']);
    $columns = $this->columns('mukurtu_bulk_edit_media');

    $columns->save('embed', $this->currentUser, ['status', 'field_not_here']);
    $this->assertSame(['name', 'status'], $columns->selected('embed', $this->currentUser));
  }

}
