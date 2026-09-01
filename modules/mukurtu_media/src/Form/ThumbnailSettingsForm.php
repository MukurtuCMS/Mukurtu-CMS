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
      $fids = $config->get($configKey) ?? [];
      $fid = reset($fids) ?: NULL;
      $form["default_thumbnail"][$key] = [
        '#type' => 'managed_file',
        '#title' => $this->t("{$value['label']} default thumbnail"),
        '#description' => $this->t("Manage default thumbnail for {$value['label']} media items."),
        '#upload_location' => 'public://thumbnail-settings',
        '#upload_validators' => [
          'FileExtension' => ['extensions' => 'png gif jpg jpeg'],
        ],
        '#default_value' => $fids,
      ];
      if ($fid && ($file = File::load($fid)) && str_starts_with($file->getMimeType(), 'image/')) {
        // Nested inside the managed_file element as a 'preview' key, the
        // same convention core's ImageWidget::process() uses for its own
        // preview element: Claro's file-managed-file.html.twig (and Gin,
        // which extends it) specifically recognizes 'preview' and renders
        // it in the widget's built-in image-preview area next to the
        // filename/remove button, rather than an arbitrary weight-sorted
        // position.
        $form["default_thumbnail"][$key]['preview'] = [
          '#theme' => 'image_style',
          '#style_name' => 'thumbnail',
          '#uri' => $file->getFileUri(),
          '#alt' => $this->t("{$value['label']} default thumbnail preview"),
          '#weight' => -20,
        ];
      }
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
