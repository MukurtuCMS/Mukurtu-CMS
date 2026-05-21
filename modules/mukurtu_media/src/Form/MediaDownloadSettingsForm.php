<?php

namespace Drupal\mukurtu_media\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Cache\Cache;

/**
 * Site-level configuration for the media download button.
 */
class MediaDownloadSettingsForm extends ConfigFormBase {

  const SETTINGS = 'mukurtu.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_media_download_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [static::SETTINGS];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);
    $enabled = $config->get('mukurtu_media_download_enabled') ?? TRUE;

    $form['mukurtu_media_download_enabled'] = [
      '#title' => $this->t('Download button'),
      '#description' => $this->t('Show or hide the download button on media assets.'),
      '#type' => 'radios',
      '#default_value' => $enabled ? 'show' : 'hide',
      '#options' => [
        'show' => $this->t('Show download button'),
        'hide' => $this->t('Hide download button'),
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('mukurtu_media_download_enabled', $form_state->getValue('mukurtu_media_download_enabled') === 'show')
      ->save();

    Cache::invalidateTags(['media_view']);

    parent::submitForm($form, $form_state);
  }

}
