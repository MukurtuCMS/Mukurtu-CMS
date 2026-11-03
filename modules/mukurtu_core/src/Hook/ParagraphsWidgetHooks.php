<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Makes the Paragraphs widget's Collapse and Expand buttons easier to use.
 *
 * - Paragraphs calls the open buttons "Edit" and "Edit all", which reads
 *   oddly next to "Collapse" and "Collapse all", so they read "Expand".
 * - Each item's button is described by that item's summary, so a screen
 *   reader says which item it opens or closes.
 * - Each button is replaced by its opposite when clicked, which drops focus
 *   to the page. The replacement is marked for
 *   js/paragraphs-widget-focus.js to focus instead.
 */
class ParagraphsWidgetHooks {

  use StringTranslationTrait;

  /**
   * Marks the button to focus after the widget's AJAX rebuild.
   */
  const FOCUS_ATTRIBUTE = 'data-mukurtu-paragraphs-focus';

  /**
   * Implements hook_field_widget_single_element_WIDGET_TYPE_form_alter().
   */
  #[Hook('field_widget_single_element_paragraphs_form_alter')]
  public function itemButtons(array &$element, FormStateInterface $form_state, array $context): void {
    $summary_id = NULL;
    foreach (['edit_button', 'collapse_button'] as $key) {
      if (!isset($element['top']['actions']['actions'][$key]['#name'])) {
        continue;
      }
      $button = &$element['top']['actions']['actions'][$key];
      if ($key === 'edit_button') {
        $this->relabel($button, (string) $this->t('Expand'));
      }

      $prefix = preg_replace('/_(edit|collapse)$/', '', $button['#name']);
      if (!empty($element['top']['summary']['fields_info']['#summary']['content'])) {
        $summary_id = Html::getId($prefix . '_summary');
        $button['#attributes']['aria-describedby'] = $summary_id;
      }
      $this->manageFocus($button, [$prefix . '_edit', $prefix . '_collapse'], $form_state);
      unset($button);
    }

    if ($summary_id) {
      // A container's #id overrides any id in its #attributes.
      $element['top']['summary']['#id'] = $summary_id;
    }
    $element['#attached']['library'][] = 'mukurtu_core/paragraphs_widget_focus';
  }

  /**
   * Implements hook_field_widget_complete_WIDGET_TYPE_form_alter().
   */
  #[Hook('field_widget_complete_paragraphs_form_alter')]
  public function headerButtons(array &$field_widget_complete_form, FormStateInterface $form_state, array $context): void {
    // While items are collapsed Expand all is the main action and Collapse
    // all sits in the dropdown; while they are open it's the other way
    // around.
    foreach (['actions', 'dropdown_actions'] as $group) {
      foreach (['edit_all', 'collapse_all'] as $key) {
        if (!isset($field_widget_complete_form['widget']['header_actions'][$group][$key]['#name'])) {
          continue;
        }
        $button = &$field_widget_complete_form['widget']['header_actions'][$group][$key];
        if ($key === 'edit_all') {
          $this->relabel($button, (string) $this->t('Expand all'));
        }
        if ($group === 'actions') {
          $prefix = preg_replace('/_(edit|collapse)_all$/', '', $button['#name']);
          $this->manageFocus($button, [$prefix . '_edit_all', $prefix . '_collapse_all'], $form_state);
        }
        unset($button);
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

  /**
   * Focuses a button after its pair was clicked, in place of Drupal's refocus.
   *
   * @param array $button
   *   The button.
   * @param string[] $pair
   *   The names of this button and its opposite.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  private function manageFocus(array &$button, array $pair, FormStateInterface $form_state): void {
    // Drupal would otherwise refocus the clicked button's nearest surviving
    // parent after our behavior has run.
    $button['#attributes']['data-disable-refocus'] = 'true';
    $trigger = $form_state->getTriggeringElement();
    if (in_array($trigger['#name'] ?? NULL, $pair, TRUE)) {
      $button['#attributes'][self::FOCUS_ATTRIBUTE] = '';
    }
  }

}
