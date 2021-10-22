<?php

namespace Drupal\mukurtu_community_records\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class MukurtuCommunityRecordSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_community_records_settings_form';
  }

  /**
   * Get the option array for CR bundle types.
   */
  private function getBundleOptions() {
    $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo('node');
    $options = [];
    foreach ($bundles as $type => $label) {
      // Skip bundles that don't have the fields to support CRs.
      if (!mukurtu_community_records_entity_type_supports_records('node', $type)) {
        continue;
      }
      $options[$type] = $label['label'];
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('mukurtu_community_records.settings');

    // Current values.
    $allowed_bundles = $config->get('allowed_community_record_bundles');

    // Build the checkboxes.
    $form['allowed_cr_bundles'] = [
      '#type' => 'checkboxes',
      '#options' => $this->getBundleOptions(),
      '#title' => $this->t('Enabled Community Record Content Types'),
      '#default_value' => $allowed_bundles,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $valid_bundles = $this->getBundleOptions();
    $selected_bundles = $form_state->getValue('allowed_cr_bundles');
    foreach ($selected_bundles as $bundle => $value) {
      if (!isset($valid_bundles[$bundle])) {
        $form_state->setErrorByName('allowed_cr_bundles', $this->t('You have selected a bundle that does not support community records.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('mukurtu_community_records.settings');

    $allowed_bundles = [];
    $form_bundles = $form_state->getValue('allowed_cr_bundles');
    foreach ($form_bundles as $bundle => $value) {
      if ($value) {
        $allowed_bundles[] = $value;
      }
    }

    // Save the new config.
    $config->set('allowed_community_record_bundles', $allowed_bundles);
    $config->save();

    return parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'mukurtu_community_records.settings',
    ];
  }

}
