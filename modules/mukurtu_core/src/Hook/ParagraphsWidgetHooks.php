<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Labels the Paragraphs widget's open buttons "Expand" to pair with "Collapse".
 *
 * Paragraphs calls them "Edit" and "Edit all", which reads oddly next to
 * "Collapse" and "Collapse all".
 */
class ParagraphsWidgetHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_field_widget_single_element_WIDGET_TYPE_form_alter().
   */
  #[Hook('field_widget_single_element_paragraphs_form_alter')]
  public function itemButtons(array &$element, FormStateInterface $form_state, array $context): void {
    if (isset($element['top']['actions']['actions']['edit_button'])) {
      $this->relabel($element['top']['actions']['actions']['edit_button'], (string) $this->t('Expand'));
    }
  }

  /**
   * Implements hook_field_widget_complete_WIDGET_TYPE_form_alter().
   */
  #[Hook('field_widget_complete_paragraphs_form_alter')]
  public function headerButtons(array &$field_widget_complete_form, FormStateInterface $form_state, array $context): void {
    // Edit all is a main action while items are collapsed, and sits in the
    // dropdown while they are open.
    foreach (['actions', 'dropdown_actions'] as $group) {
      if (isset($field_widget_complete_form['widget']['header_actions'][$group]['edit_all'])) {
        $this->relabel($field_widget_complete_form['widget']['header_actions'][$group]['edit_all'], (string) $this->t('Expand all'));
      }
    }
  }

  /**
   * Sets a button's text, and its tooltip and title where it has them.
   *
   * The button's text is also its submitted value, so it has to be set on
   * every build, including AJAX rebuilds, for Drupal to match the click.
   */
  private function relabel(array &$button, string $label): void {
    $button['#value'] = $label;
    if (isset($button['#attributes']['title'])) {
      $button['#attributes']['title'] = $label;
    }
    if (isset($button['#title'])) {
      $button['#title'] = $label;
    }
  }

}
