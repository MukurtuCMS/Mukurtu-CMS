<?php

namespace Drupal\search_api_solr\Plugin\DataType;

use Drupal\Core\TypedData\TypedData;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\search_api\Item\FieldInterface;

/**
 * Defines the "Solr field" data type.
 *
 * Instances of this class wrap Search API Field objects and allow to deal with
 * fields based upon the Typed Data API.
 *
 * @DataType(
 *   id = "solr_field",
 *   label = @Translation("Solr field"),
 *   description = @Translation("Fields from a Solr document."),
 *   definition_class = "\Drupal\search_api_solr\TypedData\SolrFieldDefinition"
 * )
 */
class SolrField extends TypedData implements \IteratorAggregate {

  /**
   * Field name.
   *
   * @var string
   */
  protected static $solrField = 'solr_field';

  /**
   * The field value(s).
   *
   * @var mixed
   */
  protected $value;

  /**
   * Creates an instance wrapping the given Field.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The Field object to wrap.
   * @param string $name
   *   The name of the wrapped field.
   * @param \Drupal\Core\TypedData\TypedDataInterface $parent
   *   The parent object of the wrapped field, which should be a Solr document.
   *
   * @return static
   */
  public static function createFromField(FieldInterface $field, $name, TypedDataInterface $parent) {
    // Get the Solr field definition from the SolrFieldManager.
    /** @var \Drupal\search_api_solr\SolrFieldManagerInterface $field_manager */
    $field_manager = \Drupal::getContainer()->get(static::$solrField . 'solr_field.manager');
    $field_id = $field->getPropertyPath();
    $definition = $field_manager->getFieldDefinitions($field->getIndex())[$field_id];
    $instance = new static($definition, $name, $parent);
    $instance->setValue($field->getValues());
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator(): \Traversable {
    return new \ArrayIterator((array) $this->value);
  }

}
