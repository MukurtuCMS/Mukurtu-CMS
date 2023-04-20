<?php

namespace Drupal\mukurtu_media\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure default thumbnail settings for this site.
 */
class ThumbnailSettingsForm extends ConfigFormBase
{
  protected $excludedMediaBundles = ['audio', 'image', 'remote_video'];

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'mukurtu_thumbnail_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames()
  {
    return ['mukurtu_thumbnail.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $config = $this->config('mukurtu_thumbnail.settings');
    $mediaBundleInfo = \Drupal::service('entity_type.bundle.info')->getBundleInfo('media');
    foreach($mediaBundleInfo as $key => $value) {
      // We do not support thumbnail generation for audio, image, and remote
      // video media items, so they are excluded from these settings.
      if (in_array($key, $this->excludedMediaBundles)) {
        continue;
      }
      $form["{$key}_default_thumbnail"] = [
        '#type' => 'managed_file',
        '#title' => $this->t("{$value['label']} default thumbnail"),
        '#description' => $this->t("Manage default thumbnail for {$value['label']} media items."),
        '#upload_location' => 'private://',
        '#upload_validators' => [
          'file_validate_extensions' => ['jpg, jpeg, png, gif'],
        ],
        '#default_value' => $config->get("{$key}_default_thumbnail"),
      ];
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    dpm($form);
    $config = $this->config('mukurtu_thumbnail.settings');
    $mediaBundleInfo = \Drupal::service('entity_type.bundle.info')->getBundleInfo('media');
    // TODO
    foreach ($mediaBundleInfo as $key => $value) {
      dpm($form_state->getValue("{$key}_default_thumbnail"));
      $config->set("{$key}_default_thumbnail", $form_state->getValue("{$key}_default_thumbnail"));
    }
    $config->save();
    parent::submitForm($form, $form_state);
  }
}
