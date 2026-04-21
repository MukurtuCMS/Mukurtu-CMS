<?php

namespace Drupal\geolocation_here\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements the HERE Maps form controller.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class HereMapsSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('here_maps.settings');

    $form['app_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('HERE Maps App ID'),
      '#default_value' => $config->get('app_id'),
      '#description' => $this->t('HERE Maps requires users to sign up at <a href="https://developer.here.com">developers.here.com</a>.'),
    ];

    $form['app_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('HERE Maps App Code'),
      '#default_value' => $config->get('app_code'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'geolocation_here_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'here_maps.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('here_maps.settings');
    $config->set('app_id', $form_state->getValue('app_id'));
    $config->set('app_code', $form_state->getValue('app_code'));

    $config->save();

    // Confirmation on form submission.
    \Drupal::messenger()->addMessage($this->t('The configuration options have been saved.'));

    drupal_flush_all_caches();
  }

}
