<?php

namespace Drupal\search_api_solr\Plugin\search_api\datasource;

use Drupal\Core\Form\FormStateInterface;

/**
 * Represents a datasource which exposes external Solr Documents.
 *
 * @SearchApiDatasource(
 *   id = "solr_multisite_document",
 *   label = @Translation("Solr Multisite Document"),
 *   description = @Translation("Search through a different site's content. (Only works if this index is attached to a Solr-based server.)"),
 * )
 */
class SolrMultisiteDocument extends SolrDocument {

  /**
   * Solr field property name.
   *
   * @var string
   */
  protected $solrField = 'solr_multisite_field';

  /**
   * Solr document property name.
   *
   * @var string
   */
  protected $solrDocument = 'solr_multisite_document';

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = parent::defaultConfiguration();
    $config['id_field'] = 'id';
    $config['language_field'] = 'ss_search_api_language';
    $config['url_field'] = 'site';

    $config['target_index'] = '';
    $config['target_index_machine_name'] = '';
    $config['target_hash'] = '';
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['target_index'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Targeted index'),
      '#required' => TRUE,
      '#description' => $this->t('Enter the prefixed name of the targeted index.'),
      '#default_value' => $this->configuration['target_index'],
    ];

    $form['target_index_machine_name'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Targeted index machine name'),
      '#required' => TRUE,
      '#description' => $this->t('Enter the machine name of the targeted index.'),
      '#default_value' => $this->configuration['target_index_machine_name'],
    ];

    $form['target_hash'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Targeted site hash'),
      '#required' => TRUE,
      '#description' => $this->t('Enter the hash of the targeted site.'),
      '#default_value' => $this->configuration['target_hash'],
    ];

    $form['id_field'] = [
      '#type' => 'value',
      '#value' => $this->configuration['id_field'],
    ];
    $form['advanced']['request_handler'] = [
      '#type' => 'value',
      '#value' => $this->configuration['request_handler'],
    ];
    $form['advanced']['default_query'] = [
      '#type' => 'value',
      '#value' => $this->configuration['default_query'],
    ];
    $form['advanced']['language_field'] = [
      '#type' => 'value',
      '#value' => $this->configuration['language_field'],
    ];
    $form['advanced']['url_field'] = [
      '#type' => 'value',
      '#value' => $this->configuration['url_field'],
    ];

    return $form;
  }

}
