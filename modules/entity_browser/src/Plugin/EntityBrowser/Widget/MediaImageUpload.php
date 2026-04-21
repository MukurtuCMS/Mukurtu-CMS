<?php

namespace Drupal\entity_browser\Plugin\EntityBrowser\Widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\entity_browser\Plugin\EntityBrowser\Widget\Upload as FileUpload;
use Drupal\media\MediaInterface;

/**
 * Uses upload to create media images.
 *
 * @EntityBrowserWidget(
 *   id = "media_image_upload",
 *   label = @Translation("Upload images as media items"),
 *   description = @Translation("Upload widget that will create media entities of the uploaded images."),
 *   auto_select = FALSE
 * )
 */
class MediaImageUpload extends FileUpload {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array_merge(parent::defaultConfiguration(), [
      'extensions' => 'jpg jpeg png gif',
      'media_type' => NULL,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $aditional_widget_parameters) {
    /** @var \Drupal\media\MediaTypeInterface $media_type */
    if (!$this->configuration['media_type'] || !($media_type = $this->entityTypeManager->getStorage('media_type')->load($this->configuration['media_type']))) {
      return ['#markup' => $this->t('The media type is not configured correctly.')];
    }

    if ($media_type->getSource()->getPluginId() != 'image') {
      return ['#markup' => $this->t('The configured media type is not using the image plugin.')];
    }

    $form = parent::getForm($original_form, $form_state, $aditional_widget_parameters);
    $form['upload']['#upload_validators']['FileExtension'] = ['extensions' => $this->configuration['extensions']];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntities(array $form, FormStateInterface $form_state) {
    $files = parent::prepareEntities($form, $form_state);

    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $media_type = $this->entityTypeManager
      ->getStorage('media_type')
      ->load($this->configuration['media_type']);

    $images = [];
    foreach ($files as $file) {
      /** @var \Drupal\media\MediaInterface $image */
      $image = $this->entityTypeManager->getStorage('media')->create([
        'bundle' => $media_type->id(),
        $media_type->getSource()->getConfiguration()['source_field'] => $file,
      ]);
      $images[] = $image;
    }

    return $images;
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$element, array &$form, FormStateInterface $form_state) {
    if (!empty($form_state->getTriggeringElement()['#eb_widget_main_submit'])) {
      $images = $this->prepareEntities($form, $form_state);
      array_walk(
        $images,
        function (MediaInterface $media) {
          $media->save();
        }
      );

      $this->selectEntities($images, $form_state);
      $this->clearFormValues($element, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['extensions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allowed extensions'),
      '#default_value' => $this->configuration['extensions'],
      '#required' => TRUE,
    ];

    $media_type_options = [];
    $media_types = $this
      ->entityTypeManager
      ->getStorage('media_type')
      ->loadByProperties(['source' => 'image']);

    foreach ($media_types as $media_type) {
      $media_type_options[$media_type->id()] = $media_type->label();
    }

    if (empty($media_type_options)) {
      $url = Url::fromRoute('entity.media_type.add_form')->toString();
      $form['media_type'] = [
        '#markup' => $this->t("You don't have media type of the Image type. You should <a href=':link'>create one</a>.", [':link' => $url]),
      ];
    }
    else {
      $form['media_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Media type'),
        '#default_value' => $this->configuration['media_type'],
        '#options' => $media_type_options,
      ];
    }

    return $form;
  }

}
