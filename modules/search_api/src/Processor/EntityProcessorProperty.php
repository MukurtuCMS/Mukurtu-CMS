<?php

namespace Drupal\search_api\Processor;

use Drupal\Core\Entity\TypedData\EntityDataDefinition;

/**
 * Provides a definition for a processor property that contains an entity.
 */
class EntityProcessorProperty extends EntityDataDefinition implements ProcessorPropertyInterface {

  /**
   * {@inheritdoc}
   */
  public function getProcessorId() {
    return $this->definition['processor_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function isHidden() {
    return !empty($this->definition['hidden']);
  }

  /**
   * {@inheritdoc}
   */
  public function isList() {
    return (bool) ($this->definition['is_list'] ?? parent::isList());
  }

}
