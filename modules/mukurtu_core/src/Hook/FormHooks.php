<?php

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\Render\Element;

/**
 * Hook implementations for mukurtu_core forms.
 */
class FormHooks {

  /**
   * Implements hook_form_FORM_ID_alter() for 'language_content_settings_form'.
   *
   * Hides og_group fields from the translation settings form to prevent users
   * from marking og_group bundle fields as translatable.
   *
   * This hook must run after the content_translation module's form alter hook
   * (\Drupal\content_translation\Hook\ContentTranslationHooks::formLanguageContentSettingsFormAlter)
   * which adds the field translation checkboxes via
   * _content_translation_form_language_content_settings_form_alter().
   */
  #[Hook('form_language_content_settings_form_alter', order: new OrderAfter(['content_translation']))]
  public function formLanguageContentSettingsFormAlter(array &$form, FormStateInterface $form_state): void {
    // Check if the settings array exists.
    if (empty($form['settings'])) {
      return;
    }

    // Loop through all entity types in the form.
    foreach (Element::children($form['settings']) as $entity_type_id) {
      // Loop through all bundles for this entity type.
      foreach (Element::children($form['settings'][$entity_type_id]) as $bundle) {
        if (empty($form['settings'][$entity_type_id][$bundle]['fields'])) {
          continue;
        }
        // Loop through all fields for this bundle.
        foreach (Element::children($form['settings'][$entity_type_id][$bundle]['fields']) as $field_name) {
          // Hide the og_group field from translation settings.
          if ($field_name !== 'og_group') {
            continue;
          }
          unset($form['settings'][$entity_type_id][$bundle]['fields'][$field_name]);

          // Also hide any column settings for the og_group field if they exist.
          if (isset($form['settings'][$entity_type_id][$bundle]['columns'][$field_name])) {
            unset($form['settings'][$entity_type_id][$bundle]['columns'][$field_name]);
          }
        }
      }
    }
  }

}
