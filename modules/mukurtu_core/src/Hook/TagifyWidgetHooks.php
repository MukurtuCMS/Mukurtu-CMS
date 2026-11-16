<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Hook implementations for the Tagify field widgets.
 */
class TagifyWidgetHooks {

  use StringTranslationTrait;

  /**
   * The Tagify module's help line, which only mentions dragging.
   */
  protected const DRAG_MESSAGE = 'Drag to re-order @entity_types.';

  /**
   * Implements hook_field_widget_single_element_WIDGET_TYPE_form_alter().
   *
   * Tags can also be moved with arrow buttons (#2710, tagify-reorder.js), so
   * say so in the help line. "Tagify with roles" replaces the line itself
   * when its rows are shown, and keeps Tagify's line when roles are off,
   * which this then replaces too.
   */
  #[Hook('field_widget_single_element_tagify_entity_reference_autocomplete_widget_form_alter')]
  #[Hook('field_widget_single_element_mukurtu_entity_reference_role_tagify_form_alter')]
  public function tagifyWidgetFormAlter(array &$element, FormStateInterface $form_state, array $context): void {
    // "Tagify with roles" wraps the Tagify element as its "names" child.
    $tagify = &$element;
    if (isset($element['names']['#type'])) {
      $tagify = &$element['names'];
    }
    if (!isset($tagify['#description'])) {
      return;
    }
    $description = &$tagify['#description'];
    if (static::isDragMessage($description)) {
      $description = $this->reorderMessage($description);
    }
    elseif (($description['#theme'] ?? NULL) === 'item_list') {
      foreach ($description['#items'] as &$item) {
        if (static::isDragMessage($item)) {
          $item = $this->reorderMessage($item);
        }
      }
    }
  }

  /**
   * Whether a value is the Tagify module's drag-only help line.
   */
  protected static function isDragMessage(mixed $value): bool {
    return $value instanceof TranslatableMarkup
      && $value->getUntranslatedString() === static::DRAG_MESSAGE;
  }

  /**
   * Builds the help line for dragging or using the arrow buttons.
   */
  protected function reorderMessage(TranslatableMarkup $drag_message): TranslatableMarkup {
    return $this->t('To change the order of @entity_types, drag them into place, or use the arrow buttons on each one.', [
      '@entity_types' => $drag_message->getArguments()['@entity_types'] ?? '',
    ]);
  }

}
