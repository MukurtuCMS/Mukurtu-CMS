<?php

namespace Drupal\geolocation_yandex\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geolocation_yandex\Plugin\geolocation\MapProvider\Yandex;

/**
 * Implements the Yandex Maps form controller.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class YandexMapsSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('geolocation_yandex.settings');

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Yandex Maps API Key'),
      '#default_value' => $config->get('api_key'),
      '#description' => $this->t('Yandex Maps requires users to sign up at <a href="https://developer.tech.yandex.ru/">developer.tech.yandex.ru</a>.'),
    ];

    $form['packages'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Yandex Maps API Packages'),
      '#options' => Yandex::getPackages(),
      '#multiple' => TRUE,
      '#default_value' => $config->get('packages'),
      '#description' => $this->t('More about <a href="https://tech.yandex.ru/maps/archive/doc/jsapi/2.0/ref/reference/packages-docpage/">packages</a>.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'geolocation_yandex_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'geolocation_yandex.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('geolocation_yandex.settings');
    $config->set('api_key', $form_state->getValue('api_key'));
    $config->set('packages', array_filter(array_values($form_state->getValue('packages'))));
    $config->save();

    // Confirmation on form submission.
    \Drupal::messenger()->addMessage($this->t('The configuration options have been saved.'));

    drupal_flush_all_caches();
  }

}
