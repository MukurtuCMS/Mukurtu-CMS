<?php

namespace Drupal\mukurtu_media\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Configure Mukurtu Media settings for this site.
 */
class MediaSettingsForm extends ConfigFormBase {

  const SETTINGS = 'mukurtu_media.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_media_settings';
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

    $upload_validators = [
      'FileExtension' => ['extensions' => 'png gif jpg jpeg webp'],
    ];

    $form['restricted_media_placeholder'] = [
      '#title' => $this->t('Restricted Media Placeholder Image'),
      '#description' => $this->t('Optional. When a user cannot view a media asset due to protocol restrictions, this image will be shown in its place. Leave empty to show nothing (default behavior).'),
      '#type' => 'managed_file',
      '#upload_location' => 'public://mukurtu/placeholder/',
      '#upload_validators' => $upload_validators,
      '#default_value' => $config->get('restricted_media_placeholder') ?? NULL,
    ];

    $form['no_media_placeholder'] = [
      '#title' => $this->t('No Media Placeholder Image'),
      '#description' => $this->t('Optional. When content has no media at all, this image will be shown in the media field. Leave empty to show nothing (default behavior).'),
      '#type' => 'managed_file',
      '#upload_location' => 'public://mukurtu/placeholder/',
      '#upload_validators' => $upload_validators,
      '#default_value' => $config->get('no_media_placeholder') ?? NULL,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    foreach (['restricted_media_placeholder', 'no_media_placeholder'] as $key) {
      $old_fids = $config->get($key) ?? [];
      $new_fids = $form_state->getValue($key) ?? [];

      // Mark newly-added files as permanent.
      foreach (array_diff($new_fids, $old_fids) as $fid) {
        $file = File::load($fid);
        if ($file) {
          $file->setPermanent();
          $file->save();
        }
      }

      // Mark removed files as temporary so Drupal's file GC can clean them up.
      foreach (array_diff($old_fids, $new_fids) as $fid) {
        $file = File::load($fid);
        if ($file) {
          $file->setTemporary();
          $file->save();
        }
      }

      $config->set($key, $new_fids);
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }

}
