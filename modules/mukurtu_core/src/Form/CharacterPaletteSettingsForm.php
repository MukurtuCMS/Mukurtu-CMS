<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Turns the character palette on or off for the whole site (#2560).
 *
 * Off by default. The characters themselves live on Language terms.
 */
class CharacterPaletteSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'mukurtu_core.character_palette';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_core_character_palette_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show the character palette'),
      '#description' => $this->t('Adds a button beside text fields and search boxes that inserts the characters listed on each Language term. Each person can still hide it.'),
      '#default_value' => (bool) $this->config(static::SETTINGS)->get('enabled'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
