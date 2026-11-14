<?php

declare(strict_types=1);

namespace Drupal\mukurtu_taxonomy\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mukurtu_core\Service\TermVisibility;
use Drupal\taxonomy\VocabularyForm;
use Drupal\taxonomy\VocabularyInterface;

/**
 * Lets a vocabulary show terms that aren't used on any content (#2643).
 *
 * Terms on restricted content stay hidden either way, since those are in use.
 *
 * @see \Drupal\mukurtu_core\Service\TermVisibility
 */
class UnusedTermsSettingHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_form_FORM_ID_alter() for taxonomy_vocabulary_form.
   */
  #[Hook('form_taxonomy_vocabulary_form_alter')]
  public function addSetting(array &$form, FormStateInterface $form_state): void {
    // The overview and delete forms share this base form ID.
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof VocabularyForm) {
      return;
    }
    $vocabulary = $form_object->getEntity();
    if (in_array($vocabulary->id(), TermVisibility::NON_CONTENT_VOCABULARIES, TRUE)) {
      return;
    }
    $form['show_unused_terms'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Show terms with no content on this vocabulary's public page"),
      '#default_value' => (bool) $vocabulary->getThirdPartySetting('mukurtu_taxonomy', 'show_unused_terms'),
    ];
    $form['#entity_builders'][] = [static::class, 'saveSetting'];
  }

  /**
   * Entity builder: saves the setting on the vocabulary.
   */
  public static function saveSetting(string $entity_type, VocabularyInterface $vocabulary, array &$form, FormStateInterface $form_state): void {
    if ($form_state->getValue('show_unused_terms')) {
      $vocabulary->setThirdPartySetting('mukurtu_taxonomy', 'show_unused_terms', TRUE);
    }
    else {
      $vocabulary->unsetThirdPartySetting('mukurtu_taxonomy', 'show_unused_terms');
    }
  }

}
