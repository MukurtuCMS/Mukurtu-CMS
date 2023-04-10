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
    $this->index = Index::load('mukurtu_browse_auto_index');
  }

  public function indexField($id, $property_path, $label, $type = 'text', $boost = 1.0) {
    if ($this->index) {
      $field = $this->index->getField($id) ?? new Field($this->index, $id);
      $field->setType($type);
      $field->setBoost($boost);
      $field->setDatasourceId("entity:{$this->entity_type_id}");
      $field->setPropertyPath($property_path);
      $field->setLabel($label);
      $this->index->addField($field);
      $this->index->save();
    }
  }

}
