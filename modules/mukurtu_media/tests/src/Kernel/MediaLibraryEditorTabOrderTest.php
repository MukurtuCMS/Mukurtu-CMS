<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Kernel;

use Drupal\media_library\MediaLibraryState;
use Drupal\mukurtu_media\MukurtuMediaLibraryUiBuilder;

/**
 * Tests the media type tab order in the media library dialog.
 *
 * @group mukurtu_media
 */
class MediaLibraryEditorTabOrderTest extends MukurtuMediaTestBase {

  /**
   * Builds the media type menu for the given opener.
   *
   * @param string $opener_id
   *   The media library opener ID.
   * @param string[] $allowed_type_ids
   *   The allowed media type IDs, in the order the opener passes them.
   *
   * @return string[]
   *   The menu link keys, in render order.
   */
  protected function buildMenuKeys(string $opener_id, array $allowed_type_ids): array {
    $builder = $this->container->get('media_library.ui_builder');
    $this->assertInstanceOf(MukurtuMediaLibraryUiBuilder::class, $builder);

    $state = MediaLibraryState::create($opener_id, $allowed_type_ids, reset($allowed_type_ids), 1);
    $method = new \ReflectionMethod($builder, 'buildMediaTypeMenu');
    $menu = $method->invoke($builder, $state);
    return array_keys($menu['#links']);
  }

  /**
   * Tests that the CKEditor dialog lists media types alphabetically.
   */
  public function testEditorTabsSortedByLabel(): void {
    // Core's CKEditor 5 media plugin moves image to the front.
    $keys = $this->buildMenuKeys('media_library.opener.editor', ['image', 'audio', 'document']);
    $this->assertSame([
      'media-library-menu-all',
      'media-library-menu-audio',
      'media-library-menu-document',
      'media-library-menu-image',
    ], $keys);
  }

  /**
   * Tests that field widget dialogs keep the configured order.
   */
  public function testFieldWidgetTabsKeepConfiguredOrder(): void {
    $keys = $this->buildMenuKeys('media_library.opener.field_widget', ['image', 'audio', 'document']);
    $this->assertSame([
      'media-library-menu-all',
      'media-library-menu-image',
      'media-library-menu-audio',
      'media-library-menu-document',
    ], $keys);
  }

}
