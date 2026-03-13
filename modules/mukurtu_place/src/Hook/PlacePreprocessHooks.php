<?php

declare(strict_types=1);

namespace Drupal\mukurtu_place\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mukurtu_place\Entity\Place;

/**
 * Hook implementations for place preprocessing.
 */
final class PlacePreprocessHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_preprocess_HOOK() for node templates.
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    $node = $variables['node'];

    if (!$node instanceof Place || $variables['view_mode'] !== 'full') {
      return;
    }

    $variables['content']['field_all_related_content']['#title'] = $this->t('Referenced Content');
  }

}
