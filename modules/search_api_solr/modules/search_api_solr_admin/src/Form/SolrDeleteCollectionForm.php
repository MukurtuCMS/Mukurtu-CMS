<?php

namespace Drupal\search_api_solr_admin\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\ServerInterface;

/**
 * The collection delete form.
 *
 * @package Drupal\search_api_solr_admin\Form
 */
class SolrDeleteCollectionForm extends SolrAdminFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'solr_delete_collection_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ServerInterface $search_api_server = NULL) {
    $this->searchApiServer = $search_api_server;

    $core = $this->searchApiServer->getBackendConfig()['connector_config']['core'];
    $form['#title'] = $this->t('Delete collection %core?', ['%core' => $core]);

    $form['actions'] = [
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $this->commandHelper->deleteCollection($this->searchApiServer->id());
      $this->messenger->addMessage($this->t('Successfully deleted collection.'));
    }
    catch (\Exception $e) {
      $this->messenger->addError($e->getMessage());
      $this->logException($e);
    }

    $form_state->setRedirect('entity.search_api_server.canonical', ['search_api_server' => $this->searchApiServer->id()]);
  }

}
