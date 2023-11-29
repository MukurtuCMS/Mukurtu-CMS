<?php

namespace Drupal\mukurtu_search\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Mukurtu search settings for this site.
 */
class SearchSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'mukurtu_search.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_search_settings';
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

    // 'db' or 'solr'.
    $backend = $config->get('backend') ?? 'db';

    $link = \Drupal\Core\Link::createFromRoute($this->t('Search API Configuration'), 'search_api.overview')->toString();

    $form['backend'] = [
      '#title' => 'Search Backend',
      '#description' => $this->t('Select which Search API backend to use for search. Note that backends must be configured prior to use. See @link.', ['@link' => $link]),
      '#type'          => 'radios',
      '#options' => [
        'db' => $this->t('Search API Database'),
        'solr' => $this->t('Search API Solr'),
      ],
      '#default_value' => $backend,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable(static::SETTINGS);

    // backend.
    $config->set('backend', $form_state->getValue('backend'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
