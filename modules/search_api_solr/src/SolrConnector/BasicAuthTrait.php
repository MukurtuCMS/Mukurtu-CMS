<?php

namespace Drupal\search_api_solr\SolrConnector;

use Drupal\Core\Form\FormStateInterface;

/**
 * Basic auth functionality for a Solr connector.
 */
trait BasicAuthTrait {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'username' => '',
      'password' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['auth'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('HTTP Basic Authentication'),
      '#description' => $this->t('If your Solr server is protected by basic HTTP authentication, enter the login data here.'),
      '#collapsible' => TRUE,
      '#collapsed' => empty($this->configuration['username']),
    ];

    $form['auth']['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $this->configuration['username'],
      '#required' => TRUE,
    ];

    $form['auth']['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#description' => $this->t('If this field is left blank and the HTTP username is filled out, the current password will not be changed.'),
    ];

    $form_state->set('previous_password', $this->configuration['password']);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    foreach ($values['auth'] as $key => $value) {
      // For password fields, there is no default value, they're empty by
      // default. Therefore we ignore empty submissions if the user didn't
      // change either.
      if ('password' === $key && '' === $value
        && isset($this->configuration['auth']['username'])
        && $values['auth']['username'] === $this->configuration['auth']['username']
      ) {
        $value = $form_state->get('previous_password');
      }

      $form_state->setValue($key, $value);
    }

    // Clean-up the form to avoid redundant entries in the stored configuration.
    $form_state->unsetValue('auth');

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function viewSettings() {
    $vars = [
      '%user' => $this->configuration['username'],
      '%pass' => str_repeat('*', strlen($this->configuration['password'])),
    ];

    $info[] = [
      'label' => $this->t('Basic HTTP authentication'),
      'info' => $this->t('Username: %user | Password: %pass', $vars),
    ];

    return $info;
  }

}
