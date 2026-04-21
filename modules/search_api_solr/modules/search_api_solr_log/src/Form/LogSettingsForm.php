<?php

namespace Drupal\search_api_solr_log\Form;

use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Solr log settings form.
 */
class LogSettingsForm extends ConfigFormBase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_api_solr_log_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['search_api_solr_log.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $config = $this->config('search_api_solr_log.settings');

    $form['days_to_keep'] = [
      '#type' => 'number',
      '#title' => $this->t('Days to keep logs'),
      '#description' => $this->t('Logged events older than the given amount of days will be deleted from Solr by cron.'),
      '#required' => TRUE,
      '#min' => 1,
      '#step' => 1,
      '#default_value' => $config->get('days_to_keep') ?? 14,
    ];

    $form['commit'] = [
      '#type' => 'select',
      '#title' => $this->t('Commit strategy'),
      '#description' => $this->t('"auto" uses Solr\'s auto commit strategy (recommended). "immediate" forces a commit after each log event. "request" forces a single commit and the end of the request.'),
      '#required' => TRUE,
      '#options' => [
        'auto' => 'auto',
        'immediate' => 'immediate',
        'request' => 'request',
      ],
      '#default_value' => $config->get('commit') ?? 'auto',
    ];

    $form['tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tags'),
      '#description' => $this->t('Comma-separated list of tags to store with each log event on this site. Useful if different sites log to the same Solr server.'),
      '#required' => FALSE,
      '#default_value' => Tags::implode($config->get('tags') ?? []),
    ];

    $form['site_hash'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Limit report to current site'),
      '#description' => $this->t('Only show log events of this Drupal site. Useful if different sites log to the same Solr server.'),
      '#required' => FALSE,
      '#default_value' => (bool) ($config->get('site_hash') ?? TRUE),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('search_api_solr_log.settings');
    $config->set('days_to_keep', (int) $form_state->getValue('days_to_keep'))->save();
    $config->set('commit', (string) $form_state->getValue('commit'))->save();
    $config->set('tags', Tags::explode($form_state->getValue('tags')))->save();
    $config->set('site_hash', (bool) $form_state->getValue('site_hash'))->save();
    parent::submitForm($form, $form_state);
  }

}
