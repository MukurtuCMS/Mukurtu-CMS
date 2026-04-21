<?php

namespace Drupal\geolocation_baidu\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements the Baidu Maps form controller.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class BaiduMapsSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('baidu_maps.settings');

    $form['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Baidu Maps App ID'),
      '#default_value' => $config->get('key'),
      '#description' => $this->t('Baidu Maps requires users to sign up at <a href="@link_baidu_api" target="_blank">Baidu Map API Key</a>.', [
        '@link_baidu_api' => 'https://lbsyun.baidu.com/apiconsole/key',
      ]),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'geolocation_baidu_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'baidu_maps.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('baidu_maps.settings');
    $config->set('key', $form_state->getValue('key'));

    $config->save();

    // Confirmation on form submission.
    \Drupal::messenger()->addMessage($this->t('The configuration options have been saved.'));

    drupal_flush_all_caches();
  }

}
