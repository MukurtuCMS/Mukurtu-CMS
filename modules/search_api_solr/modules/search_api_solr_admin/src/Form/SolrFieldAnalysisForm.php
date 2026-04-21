<?php

namespace Drupal\search_api_solr_admin\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\SolrFieldTypeInterface;
use Solarium\Core\Query\Result\ResultInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for solr field analysis.
 */
class SolrFieldAnalysisForm extends FormBase {

  /**
   * Search api server.
   *
   * @var \Drupal\search_api\ServerInterface
   */
  protected $server;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor for SolrFieldAnalysisForm.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'solr_field_analysis_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Nothing for submission. This is only because this method is abstract.
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ServerInterface $search_api_server = NULL) {
    $this->server = $search_api_server;

    $form['index_query_details'] = [
      '#type' => 'details',
      '#title' => $this->t('Field values'),
      '#open' => TRUE,
    ];

    $form['index_query_details']['index_value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Field index value'),
    ];

    $form['index_query_details']['query_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field query value'),
    ];

    // Get solr field lists.
    $list_builder = $this->entityTypeManager->getListBuilder('solr_field_type');
    $list_builder->setServer($search_api_server);
    /** @var SolrFieldTypeInterface[] $solr_field_types */
    $solr_field_types = $list_builder->load();

    $solr_fields = [];
    foreach ($solr_field_types as $solr_field_type) {
      $solr_fields[$solr_field_type->getFieldTypeName()] = $solr_field_type->label();
    }

    $form['analysis_field'] = [
      '#type' => 'select',
      '#options' => $solr_fields,
      '#required' => TRUE,
      '#title' => $this->t('Solr field type'),
    ];

    $form['#attached']['library'][] = 'search_api_solr_admin/solr_field_analysis';

    $form['submit'] = [
      '#type' => 'button',
      '#value' => $this->t('Perform Analysis'),
      '#ajax' => [
        'callback' => '::ajaxFieldAnalysis',
      ],
    ];

    $form['analysis_result'] = [
      '#markup' => '<div id="analysis-result"></div>',
    ];

    return $form;
  }

  /**
   * Ajax callback for field analysis submission.
   *
   * @param array $form
   *   Form object.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Ajax Response.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function ajaxFieldAnalysis(array $form, FormStateInterface $form_state): AjaxResponse {
    $field = $form_state->getValue('analysis_field');
    $index_value = trim($form_state->getValue('index_value'));
    $query_value = trim($form_state->getValue('query_value'));

    $build = [];

    if ($field && ($index_value || $query_value)) {
      /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
      $backend = $this->server->getBackend();
      $connector = $backend->getSolrConnector();
      $analysisQueryField = $connector->getAnalysisQueryField();
      $analysisQueryField->setFieldType($field);
      if ($index_value) {
        $analysisQueryField->setFieldValue($index_value);
      }
      if ($query_value) {
        $analysisQueryField->setQuery($query_value);
      }
      $analysisQueryField->setShowMatch(TRUE);

      $results = $connector->analyze($analysisQueryField);

      if ($index_value) {
        // Prepare index analysis rendering data.
        $index_processed_data = $this->getIndexDataFromResult($results, 'index');
        $build[] = [
          '#theme' => 'solr_field_analysis',
          '#title' => $this->t('Index Analysis'),
          '#data' => $index_processed_data,
        ];
      }

      if ($query_value) {
        // Prepare query analysis rendering data.
        $query_processed_data = $this->getIndexDataFromResult($results, 'query');
        $build[] = [
          '#theme' => 'solr_field_analysis',
          '#title' => $this->t('Query Analysis'),
          '#data' => $query_processed_data,
        ];
      }
    }

    $ajax_response = new AjaxResponse();
    $ajax_response->addCommand(new HtmlCommand('#analysis-result', $build));

    return $ajax_response;
  }

  /**
   * Prepare the Index data result set for rendering.
   *
   * @param \Solarium\Core\Query\Result\ResultInterface $results
   *   Analyse query result.
   * @param string $type
   *   Type of analysis - 'index' or 'query'.
   */
  protected function getIndexDataFromResult(ResultInterface $results, string $type): array {
    $data = [];
    foreach ($results as $result) {
      foreach ($result as $item) {
        if ($type === 'query') {
          $indexAnalysis = $item->getQueryAnalysis();
        }
        else {
          $indexAnalysis = $item->getIndexAnalysis();
        }

        if (!empty($indexAnalysis)) {
          foreach ($indexAnalysis as $classes) {
            $class_name = $classes->getName();
            $exploded_name = explode('.', $class_name);
            $class_name = end($exploded_name);
            $data[$class_name] = [];
            foreach ($classes as $class) {
              $data[$class_name][] = [
                'text' => $class->getText(),
                'raw_text' => $class->getRawText(),
                'matches' => $class->getMatch(),
              ];
            }
          }
        }
      }
    }

    return $data;
  }

}
