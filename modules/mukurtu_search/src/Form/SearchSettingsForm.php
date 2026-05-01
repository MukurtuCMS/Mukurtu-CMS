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

    $form['browse_display'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Default search result settings'),
      '#description' => $this->t('These settings control the default results on all search and browse pages. Collapsing and hiding pages gives a cleaner results page. This does not affect the actual search, indexing, or access, just what scope of content is displayed on the results page. Users can toggle these settings on and off on the search pages while they browse.'),
    ];

    $form['browse_display']['collapse_multipage_pages'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Collapse multipage items to first page only'),
      '#description' => $this->t('When enabled, only the first page of each multipage item appears in browse and search results. Users can override this with the <code>?mpi_collapse=0</code> URL parameter.'),
      '#default_value' => $config->get('collapse_multipage_pages') ?? FALSE,
    ];

    $form['browse_display']['collapse_community_records'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide community records'),
      '#description' => $this->t('When enabled, community records are hidden from browse and search results; only original records appear. Users can override this with the <code>?cr_collapse=0</code> URL parameter.'),
      '#default_value' => $config->get('collapse_community_records') ?? FALSE,
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
    $config->set('collapse_multipage_pages', (bool) $form_state->getValue('collapse_multipage_pages'));
    $config->set('collapse_community_records', (bool) $form_state->getValue('collapse_community_records'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
