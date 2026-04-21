<?php

namespace Drupal\search_api_solr\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Form\IndexAddFieldsForm;
use Drupal\search_api_solr\Plugin\search_api\datasource\SolrDocument;

/**
 * Provides a form for adding fields to a search index.
 */
class IndexAddSolrDocumentFieldsForm extends IndexAddFieldsForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_api_index.add_solr_document_fields';
  }

  /**
   * {@inheritdoc}
   */
  protected function buildDatasourcesForm(array $form, FormStateInterface $form_state): array {
    $datasources = [
      '' => NULL,
    ];
    $datasources += $this->entity->getDatasources();
    foreach ($datasources as $datasource_id => $datasource) {
      if ($datasource instanceof SolrDocument) {
        $item = $this->getDatasourceListItem($datasource);
        if ($item) {
          foreach ($this->entity->getFieldsByDatasource($datasource_id) as $field) {
            if (empty($item['table']['#rows'])) {
              continue;
            }
            $property = $field->getPropertyPath();

            // Fields to add are stored in rows with the machine name column
            // matching the field property. Remove any table rows that have
            // already been added.
            foreach ($item['table']['#rows'] as $key => $row) {
              if (empty($row['machine_name']['data'])) {
                continue;
              }

              if ($row['machine_name']['data'] === $property) {
                unset($item['table']['#rows'][$key]);
              }
            }
          }

          $form['datasources']['datasource_' . $datasource_id] = $item;
        }
      }
    }
    return $form;
  }

}
