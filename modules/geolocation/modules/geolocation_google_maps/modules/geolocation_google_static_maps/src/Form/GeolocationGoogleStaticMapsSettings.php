<?php

namespace Drupal\geolocation_google_static_maps\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements the GeolocationGoogleMapAPIkey form controller.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class GeolocationGoogleStaticMapsSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('geolocation_google_static_maps.settings');

    $form['google_static_maps_url_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Google Static Maps URL signage secret'),
      '#default_value' => $config->get('google_static_maps_url_secret'),
      '#description' => $this->t('Used in conjunction with an API key, a URL signing secret can tag API requests with a higher degree of security. To protect against unauthorised usage, requests without a signature are subject to a limit of 25,000 requests per day.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'geolocation_google_static_maps_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'geolocation_google_static_maps.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('geolocation_google_static_maps.settings');
    $config->set('google_static_maps_url_secret', $form_state->getValue('google_static_maps_url_secret'));

    $config->save();

    // Confirmation on form submission.
    \Drupal::messenger()->addMessage($this->t('The configuration options have been saved.'));

    drupal_flush_all_caches();
  }

}
