<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\mukurtu_core\CharacterPalette;

/**
 * Hook implementations for the character palette (#2560).
 */
final class CharacterPaletteHooks {

  /**
   * Implements hook_element_info_alter().
   *
   * One #process callback on every text field, text area and search box
   * reaches edit forms and exposed search filters alike.
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(array &$info): void {
    foreach (CharacterPalette::ELEMENT_TYPES as $type) {
      if (isset($info[$type])) {
        $info[$type]['#process'][] = [CharacterPalette::class, 'processElement'];
      }
    }
  }

  /**
   * Implements hook_page_attachments().
   *
   * The characters go on every page while the palette is on, rather than
   * with each marked element, because the theme's header search is plain
   * markup with no #process to attach them.
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    $cacheability = CharacterPalette::cacheability();
    $groups = CharacterPalette::groups();
    if ($groups !== []) {
      $attachments['#attached']['library'][] = 'mukurtu_core/character_palette';
      $attachments['#attached']['drupalSettings']['mukurtuCharacterPalette']['groups'] = $groups;
    }
    $cacheability->merge(BubbleableMetadata::createFromRenderArray($attachments))->applyTo($attachments);
  }

}
