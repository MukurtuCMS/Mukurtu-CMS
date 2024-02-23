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

    $form['mukurtu_default_image'] = [
      '#title' => 'Default Image',
      '#description' => $this->t('This image will be used when media is not available for a field or item.'),
      '#type'          => 'entity_autocomplete',
      '#target_type'   => 'media',
      '#default_value' => $default_media,
    ];

    $defaultRelatedContentOption = $config->get('mukurtu_related_content_display') ?? 'computed';

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

    // Show related content teasers by default.
    $defaultRelatedContentTeasersTopRightOption = $config->get('mukurtu_related_content_teasers_display_top_right') ?? 'show';
    $defaultRelatedContentTeasersBottomOption = $config->get('mukurtu_related_content_teasers_display_bottom') ?? 'show';

    $form['mukurtu_related_content_teasers_display_top_right'] = [
      '#title' => 'Related Content Teasers Display -- Top Right',
      '#description' => $this->t('Toggle visibility of related content teasers at the top right of content items.'),
      '#type' => 'radios',
      '#default_value' => $defaultRelatedContentTeasersTopRightOption,
      '#options' => [
        'show' => $this->t('Show'),
        'hide' => $this->t('Hide'),
      ],
    ];
    $form['mukurtu_related_content_teasers_display_bottom'] = [
      '#title' => 'Related Content Teasers Display -- Bottom',
      '#description' => $this->t('Toggle visibility of related content teasers at the bottom of content items.'),
      '#type' => 'radios',
      '#default_value' => $defaultRelatedContentTeasersBottomOption,
      '#options' => [
        'show' => $this->t('Show'),
        'hide' => $this->t('Hide'),
      ],
    ];

    $bundleInfo = \Drupal::service('entity_type.bundle.info')->getBundleInfo('node');

    $form['citation_templates'] = [
      '#type' => 'details',
      '#title' => $this->t('Citation Field Templates'),
    ];

    foreach ($bundleInfo as $bundleName => $bundleValue) {
      $form['citation_templates'][$bundleName] = [
        '#type' => 'textarea',
        '#title' => $this->t($bundleValue['label'] . ' Citation Template'),
        '#description' => $this->t('Manage citation template for ' . $bundleValue['label'] . '.'),
        '#default_value' => $config->get($bundleName) ?? '',
      ];

      // Add the token tree UI.
      $form['citation_templates']["{$bundleName}_token_wrapper"] = [
        '#type' => 'item',
      ];

      $form['citation_templates']["{$bundleName}_token_wrapper"]["token_tree_$bundleName"] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['user', 'node'],
        '#show_restricted' => FALSE,
        '#weight' => 90,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable(static::SETTINGS);
    $bundleInfo = \Drupal::service('entity_type.bundle.info')->getBundleInfo('node');

    // Citation templates.
    foreach ($bundleInfo as $bundle => $bundleValue) {
      $config->set($bundle, $form_state->getValue($bundle));
    }

    // Default Image.
    $config->set('mukurtu_default_image', $form_state->getValue('mukurtu_default_image'));

    // Related content.
    $config->set('mukurtu_related_content_display', $form_state->getValue('mukurtu_related_content_display'));

    // Related content teasers.
    $config->set('mukurtu_related_content_teasers_display_top_right', $form_state->getValue('mukurtu_related_content_teasers_display_top_right'));
    $config->set('mukurtu_related_content_teasers_display_bottom', $form_state->getValue('mukurtu_related_content_teasers_display_bottom'));

    $config->save();

    // Computed citation field needs the node_view tag invalidated.
    Cache::invalidateTags(['node_view']);

    parent::submitForm($form, $form_state);
  }

}
