<?php

namespace Drupal\search_api_solr\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Form\IndexForm;

/**
 * Provides a form for the Index entity.
 */
class IndexSolrMultisiteUpdateForm extends IndexSolrMultisiteCloneForm {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function form(array $form, FormStateInterface $form_state) {
    // If the form is being rebuilt, rebuild the entity with the current form
    // values.
    if ($form_state->isRebuilding()) {
      // When the form is being built for an AJAX response the ID is not present
      // in $form_state. To ensure our entity is always valid, we're adding the
      // ID back.
      if (!$this->entity->isNew()) {
        $form_state->setValue('id', $this->entity->id());
      }
      $this->entity = $this->buildEntity($form, $form_state);
    }

    if (!$this->entity->isNew()) {
      /** @var \Drupal\search_api\ServerInterface $server */
      $server = $this->entity->getServerInstance();
      /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
      $backend = $server->getBackend();

      /** @var \Drupal\search_api\Datasource\DatasourceInterface $datasource */
      $datasource = $this->entity->getDatasource('solr_multisite_document');

      /** @var \Drupal\search_api\IndexInterface $target_index */
      $target_index = $this->entityTypeManager->getStorage('search_api_index')->load(
        $datasource->getConfiguration()['target_index_machine_name']
      );

      $fields = $target_index->getFields();
      $solr_field_names = $backend->getSolrFieldNames($target_index);

      foreach ($fields as $field_id => $field) {
        $field->setDatasourceId('solr_multisite_document');
        $field->setConfiguration([]);
        $field->setPropertyPath($solr_field_names[$field_id]);
      }

      $this->entity->setFields($fields);
      $this->entity->setProcessors($target_index->getProcessors());

      $target_index_prefixed = $backend->getTargetedIndexId($target_index);
    }

    $form = IndexForm::form($form, $form_state);

    $arguments = ['%label' => $this->entity->label()];
    $form['#title'] = $this->t('Update multisite search index %label', $arguments);

    $this->buildEntityForm($form, $form_state, $this->entity);

    $form['datasource_configs']['solr_multisite_document']['target_index']['#default_value'] = $target_index_prefixed;

    return $form;
  }

}
