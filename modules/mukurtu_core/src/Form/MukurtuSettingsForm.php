<?php

namespace Drupal\mukurtu_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure example settings for this site.
 */
class MukurtuSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'mukurtu.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_admin_settings';
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
    $config = $this->config(static::SETTINGS);

    $default_media_id = $config->get('mukurtu_default_image');
    if ($default_media_id) {
      $default_media = \Drupal::entityTypeManager()->getStorage('media')->load($default_media_id);
    } else {
      $default_media = NULL;
    }

    $form['mukurtu_default_image'] = [
      '#title' => 'Default Image',
      '#description' => $this->t('This image will be used when media is not available for a field or item.'),
      '#type'          => 'entity_autocomplete',
      '#target_type'   => 'media',
      '#default_value' => $default_media,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('mukurtu_default_image', $form_state->getValue('mukurtu_default_image'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
