<?php

declare(strict_types=1);

namespace Drupal\mukurtu_taxonomy\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the vocabulary settings form.
 */
class VocabularyFormHooks {

  /**
   * Implements hook_form_FORM_ID_alter() for taxonomy_vocabulary_form.
   *
   * og_ui adds an Organic groups section to every bundle settings form.
   * Mukurtu manages groups through communities and protocols, so a vocabulary
   * never needs it, and its checkboxes would add Organic Groups fields to terms
   * outside that system (#2606).
   */
  #[Hook('form_taxonomy_vocabulary_form_alter')]
  public function hideOrganicGroups(array &$form, FormStateInterface $form_state): void {
    $form['#process'][] = [static::class, 'hideOrganicGroupsSection'];
  }

  /**
   * Form #process callback: hides og_ui's section.
   *
   * It runs as #process so that it follows og_ui's form alter, whichever module
   * runs first. The section is hidden, not removed, because its validation
   * expects it, and hidden elements keep their current values, so saving the
   * form never changes a vocabulary's group settings.
   */
  public static function hideOrganicGroupsSection(array $element): array {
    if (isset($element['og'])) {
      $element['og']['#access'] = FALSE;
    }
    return $element;
  }

}
