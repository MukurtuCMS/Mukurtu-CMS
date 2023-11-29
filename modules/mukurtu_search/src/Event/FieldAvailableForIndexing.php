<?php

namespace Drupal\mukurtu_search\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Item\Field;

/**
 * Event for related content field computation.
 */
class FieldAvailableForIndexing extends Event {

  const NEW_FIELD = 'mukurtu_search_new_field_available';
  const UPDATED_FIELD = 'mukurtu_search_updated_field_available';

  /**
   * The Search API index.
   *
   * @var \Drupal\search_api\Entity\Index
   */
  public $index;

  /**
   * The entity type ID.
   *
   * @var string
   */
  public $entity_type_id;

  /**
   * The entity bundle.
   *
   * @var string
   */
  public $bundle;

  /**
   * The field definition.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface
   */
  public $field_definition;

  /**
   * Constructs the object.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node having its related content computed.
   */
  public function __construct($entity_type_id, $bundle, $field_definition) {
    $this->entity_type_id = $entity_type_id;
    $this->bundle = $bundle;
    $this->field_definition = $field_definition;
  }

  /**
   * Index a field in a SAPI index or indexes.
   *
   * @param mixed $index_ids
   *   The machine name of the SAPI index or indexes to index the field in.
   * @param string $id
   *   The SAPI field ID.
   * @param string $property_path
   *   The SAPI field property path.
   * @param string $label
   *   The SAPI field label.
   * @param string $type
   *   The SAPI type to index the field as. E.g., 'text', 'fulltext'...
   * @param float $boost
   *   The SAPI boost setting for the field.
   *
   * @return void
   */
  public function indexField($index_ids, $id, $property_path, $label, $type = 'text', $boost = 1.0) {
    $indexes = is_array($index_ids) ? $index_ids : [$index_ids];
    foreach ($indexes as $index_id) {
      if ($index = Index::load($index_id)) {
        $field = $index->getField($id) ?? new Field($index, $id);
        $field->setType($type);
        $field->setBoost($boost);
        $field->setDatasourceId("entity:{$this->entity_type_id}");
        $field->setPropertyPath($property_path);
        $field->setLabel($label);
        $index->addField($field);

        // Make text ignore case by default. We want strings to be excluded.
        if ($type === 'text') {
          $processors = $index->getProcessors();
          if ($processors && isset($processors['ignorecase'])) {
            $ignorecaseConfig = $processors['ignorecase']->getConfiguration();
            if (isset($ignorecaseConfig['fields']) && !in_array($id, $ignorecaseConfig['fields'])) {
              $ignorecaseConfig['fields'][] = $id;
              $processors['ignorecase']->setConfiguration($ignorecaseConfig);
              $index->setProcessors($processors);
            }
          }
        }
        try {
          $index->save();
        } catch (\Exception $e) {
          // IDK? Give up but don't crash!
        }
      }
    }
  }

}
