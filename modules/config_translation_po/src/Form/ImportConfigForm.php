<?php

namespace Drupal\config_translation_po\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\locale\Form\ImportForm;

/**
 * Form constructor for the translation import screen.
 *
 * @internal
 */
class ImportConfigForm extends ImportForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_translation_import_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->moduleHandler->loadInclude('locale', 'translation.inc');
    $langcode = $form_state->getValue('langcode');
    $language = $this->languageManager->getLanguage($langcode);
    if (empty($language)) {
      // Language is not enabled.
      $list = $this->languageManager->getStandardLanguageList();
      $language_name = $list[$langcode][0] ?: $langcode;
      $this->messenger()->addStatus($this->t('The language %langcode is not enabled.', ['%langcode' => $language_name]));
      return;
    }
    $options = array_merge(_locale_translation_default_update_options(), [
      'langcode' => $form_state->getValue('langcode'),
      'overwrite_options' => $form_state->getValue('overwrite_options'),
      'customized' => $form_state->getValue('customized') ? LOCALE_CUSTOMIZED : LOCALE_NOT_CUSTOMIZED,
    ]);

    // Update locale translations.
    $this->moduleHandler->loadInclude('locale', 'bulk.inc');
    $file = locale_translate_file_attach_properties($this->file, $options);
    $batch = locale_translate_batch_build([$file->uri => $file], $options);
    batch_set($batch);

    // Create or update all configuration translations for this language.
    $this->moduleHandler->loadInclude('config_translation_po', 'bulk.inc');
    if ($batch = config_translation_po_config_batch_update_components($options, [$form_state->getValue('langcode')])) {
      batch_set($batch);
    }
  }

}
