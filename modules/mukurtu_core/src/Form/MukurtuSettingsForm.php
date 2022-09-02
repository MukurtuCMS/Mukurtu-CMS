<?php

namespace Drupal\mukurtu_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Cache\Cache;

/**
 * Configure Mukurtu core settings for this site.
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
    }
    else {
      $default_media = NULL;
    }

    $defaultRelatedContentOption = $config->get('mukurtu_related_content_display') ?? 'computed';

    $form['mukurtu_default_image'] = [
      '#title' => 'Default Image',
      '#description' => $this->t('This image will be used when media is not available for a field or item.'),
      '#type'          => 'entity_autocomplete',
      '#target_type'   => 'media',
      '#default_value' => $default_media,
    ];

    $form['mukurtu_related_content_display'] = [
      '#title' => 'Related Content Display',
      '#description' => $this->t('Select what content should be displayed for the related content field.'),
      '#type' => 'radios',
      '#default_value' => $defaultRelatedContentOption,
      '#options' => [
        'localonly' => $this->t('Display value of the item\'s related content field as is.'),
        'computed' => $this->t('Display value of the item\'s related content field, but also include content that includes this item as related content.'),
      ],
    ];

    $bundleInfo = \Drupal::service('entity_type.bundle.info')->getBundleInfo('node');

    foreach ($bundleInfo as $bundleName => $bundleValue) {
      $form['citation_templates'][$bundleName] = [
        '#type' => 'textarea',
        '#title' => $this->t($bundleValue['label'] . ' Citation Template'),
        '#description' => $this->t('Manage citation template for ' . $bundleValue['label'] . '.'),
        '#default_value' => $config->get($bundleName) ?? '',
      ];
    }

    $form['token_tree'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['user', 'node'],
      '#show_restricted' => FALSE,
      '#weight' => 90,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $bundleInfo = \Drupal::service('entity_type.bundle.info')->getBundleInfo('node');

    foreach ($bundleInfo as $bundle => $bundleValue) {
      $this->configFactory->getEditable(static::SETTINGS)
        ->set($bundle, $form_state->getValue($bundle))
        ->save();
    }

    $this->configFactory->getEditable(static::SETTINGS)
      ->set('mukurtu_default_image', $form_state->getValue('mukurtu_default_image'))
      ->set('mukurtu_related_content_display', $form_state->getValue('mukurtu_related_content_display'))
      ->save();

    //Cache::invalidateTags(['node_view']);
    parent::submitForm($form, $form_state);
  }

}
