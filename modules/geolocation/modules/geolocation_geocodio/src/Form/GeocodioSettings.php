<?php

namespace Drupal\geolocation_geocodio\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements the Geocodio form controller.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class GeocodioSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('geolocation_geocodio.settings');

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Geocodio API Key'),
      '#default_value' => $config->get('api_key'),
      '#description' => $this->t('Geocodio requires users to sign up at <a href="https://dash.geocod.io/apikey/create">geocod.io</a>.'),
    ];

    $form['fields'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Geocodio Fields'),
      '#default_value' => $config->get('fields'),
      '#description' => $this->t("Fields available in the Geocodio API will be added to results if you add them here in a string delimited list such as 'cd,stateleg'."),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'geolocation_geocodio_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'geolocation_geocodio.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('geolocation_geocodio.settings');
    $config->set('api_key', $form_state->getValue('api_key'));
    $config->set('fields', $form_state->getValue('fields'));
    $config->save();

    // Confirmation on form submission.
    \Drupal::messenger()->addMessage($this->t('The configuration options have been saved.'));

    drupal_flush_all_caches();
  }

}
