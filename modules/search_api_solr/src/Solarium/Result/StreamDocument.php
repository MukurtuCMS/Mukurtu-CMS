<?php

namespace Drupal\search_api_solr\Solarium\Result;

use Solarium\Core\Query\AbstractDocument;

/**
 * Stream result Solr document.
 */
class StreamDocument extends AbstractDocument {

  /**
   * Constructor.
   *
   * @param array $fields
   *   The array of fields.
   */
  public function __construct(array $fields) {
    $this->fields = $fields;
  }

  /**
   * Sets a field value.
   *
   * @param string $name
   *   The field name.
   * @param mixed $value
   *   The field value.
   */
  public function __set($name, $value): void {
    $this->fields[$name] = $value;
  }

  #[\ReturnTypeWillChange]

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize() {
    return $this->getFields();
  }

}
