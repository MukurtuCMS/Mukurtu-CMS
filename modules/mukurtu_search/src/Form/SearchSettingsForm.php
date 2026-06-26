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

    $form['browse_display'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Default search result settings'),
      '#description' => $this->t('These settings control the default results on all search and browse pages. Hiding pages and community records gives a cleaner results page, but makes those components less visible. This does not affect the actual search, indexing, or access, just what scope of content is displayed on the results page. Users can toggle these settings on and off on the search pages while they browse.'),
    ];

    $form['browse_display']['collapse_multipage_pages'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show all multipage item pages in search results'),
      '#description' => $this->t('When enabled, all pages of each multipage item appear in browse and search results. When disabled, only the first pages appear. Users can change this setting as they browse.'),
      '#default_value' => !($config->get('collapse_multipage_pages') ?? TRUE),
    ];

    $form['browse_display']['collapse_community_records'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show community records in search results'),
      '#description' => $this->t('When enabled, community records are shown in browse and search results. When disabled, only the original records appear. Users can change this setting as they browse.'),
      '#default_value' => !($config->get('collapse_community_records') ?? FALSE),
    ];

    $form['header_search'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Header search'),
      '#description' => $this->t('Controls the search box that appears in the header navigation bar.'),
    ];

    $form['header_search']['header_search_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show search box in the header navigation'),
      '#default_value' => (bool) ($config->get('header_search_enabled') ?? TRUE),
    ];

    $form['header_search']['header_search_path'] = [
      '#type' => 'select',
      '#title' => $this->t('Search destination'),
      '#description' => $this->t('Users will be sent to this page with their search query.'),
      '#options' => [
        '/browse' => $this->t('Browse (/browse)'),
        '/digital-heritage' => $this->t('Digital Heritage (/digital-heritage)'),
      ],
      '#default_value' => $config->get('header_search_path') ?? '/browse',
      '#states' => [
        'visible' => [
          ':input[name="header_search_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['backend'] = [
      '#title' => $this->t('Search Backend'),
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

    $config->set('backend', $form_state->getValue('backend'));
    $config->set('collapse_multipage_pages', !(bool) $form_state->getValue('collapse_multipage_pages'));
    $config->set('collapse_community_records', !(bool) $form_state->getValue('collapse_community_records'));
    $config->set('header_search_enabled', (bool) $form_state->getValue('header_search_enabled'));
    $allowed_paths = ['/browse', '/digital-heritage'];
    $path = $form_state->getValue('header_search_path');
    $config->set('header_search_path', in_array($path, $allowed_paths, TRUE) ? $path : '/browse');
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
