<?php

namespace Drupal\mukurtu_multipage_items\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure mukurtu_multipage_items settings for this site.
 */
class MultipageItemConfigForm extends ConfigFormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'mukurtu_multipage_items_multipage_config';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames()
  {
    return ['mukurtu_multipage_items.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $config = $this->config('mukurtu_multipage_items.settings');
    $checked = array_keys(array_filter($config->get('bundles_config')));
    $bundleInfo = \Drupal::service('entity_type.bundle.info')->getBundleInfo('node');
    $bundleNames = [];
    $preEnabledBundles = [];
    $options = [];

    // Fetch all bundles.
    foreach ($bundleInfo as $bundleName => $bundleValue) {
      $options[$bundleName] = $this->t($bundleValue['label']);
      array_push($bundleNames, $bundleName);

      $result = \Drupal::entityQuery('multipage_item')
        ->condition('field_pages.entity:node.type', $bundleName)
        ->accessCheck(FALSE)
        ->execute();

      if ($result) {
        array_push($preEnabledBundles, $bundleName);
      }
    }

    $form['bundles_config'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#title' => $this->t('Enable bundles for multipage items:'),
      '#default_value' => $checked,
    ];

    // Disable checkboxes for already existing bundles.
    foreach ($preEnabledBundles as $bundle) {
      $form['bundles_config'][$bundle] = [
        '#default_value' => TRUE,
        '#disabled' => TRUE,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $this->config('mukurtu_multipage_items.settings')
      ->set('bundles_config', $form_state->getValue('bundles_config'))
      ->save();

      parent::submitForm($form, $form_state);
  }
}
