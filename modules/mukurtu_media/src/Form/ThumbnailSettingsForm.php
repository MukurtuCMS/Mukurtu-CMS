<?php

namespace Drupal\mukurtu_media\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Configure default thumbnail settings for this site.
 */
class ThumbnailSettingsForm extends ConfigFormBase
{
  protected $excludedMediaBundles = [];

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
      if (in_array($key, $this->excludedMediaBundles)) {
        continue;
      }
      $configKey = $this->getConfigKey($key);
      $form["default_thumbnail"][$key] = [
        '#type' => 'managed_file',
        '#title' => $this->t("{$value['label']} default thumbnail"),
        '#description' => $this->t("Manage default thumbnail for {$value['label']} media items."),
        '#upload_location' => 'public://thumbnail-settings',
        '#upload_validators' => [
          'FileExtension' => ['extensions' => 'png gif jpg jpeg'],
        ],
        '#default_value' => $config->get($configKey) ?? NULL,
      ];
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $config = $this->config('mukurtu_thumbnail.settings');
    $mediaBundleInfo = \Drupal::service('entity_type.bundle.info')->getBundleInfo('media');
    foreach ($mediaBundleInfo as $key => $value) {
      $formFile = $form_state->getValue($key);
      if (isset($formFile[0]) && !empty($formFile[0])) {
        $file = File::load($formFile[0]);
        $file->setPermanent();
        $file->save();
      }
      $config->set($this->getConfigKey($key), $formFile);
    }
    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Returns the config key for a given media bundle.
   *
   * Audio and video use a suffixed key to avoid collisions with legacy config.
   */
  protected function getConfigKey(string $bundle): string {
    static $suffixed = ['audio', 'video'];
    return in_array($bundle, $suffixed) ? $bundle . '_default_thumbnail' : $bundle;
  }
}
