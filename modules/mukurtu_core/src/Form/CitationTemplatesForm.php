<?php

namespace Drupal\mukurtu_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure citation field templates for this site.
 */
class CitationTemplatesForm extends ConfigFormBase {

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
    return 'mukurtu_citation_templates';
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
    $bundleInfo = \Drupal::service('entity_type.bundle.info')->getBundleInfo('node');

    $form['citation_templates'] = [
      '#type' => 'details',
      '#title' => $this->t('Citation Field Templates'),
      '#open' => TRUE,
    ];

    foreach ($bundleInfo as $bundleName => $bundleValue) {
      $form['citation_templates'][$bundleName] = [
        '#type' => 'textarea',
        '#title' => $this->t('@label Citation Template', ['@label' => $bundleValue['label']]),
        '#description' => $this->t('Manage citation template for @label.', ['@label' => $bundleValue['label']]),
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

    foreach ($bundleInfo as $bundle => $bundleValue) {
      $config->set($bundle, $form_state->getValue($bundle));
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
