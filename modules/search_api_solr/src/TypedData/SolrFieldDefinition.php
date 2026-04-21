<?php

namespace Drupal\search_api_solr\TypedData;

use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines a class for Solr field definitions.
 */
class SolrFieldDefinition extends DataDefinition implements SolrFieldDefinitionInterface {

  /**
   * Human-readable labels for Solr schema properties.
   *
   * @var string[]
   */
  protected static $schemaLabels = [
    'I' => 'Indexed',
    'T' => 'Tokenized',
    'S' => 'Stored',
    'M' => 'Multivalued',
    'V' => 'TermVector Stored',
    'o' => 'Store Offset With TermVector',
    'p' => 'Store Position With TermVector',
    'O' => 'Omit Norms',
    'L' => 'Lazy',
    'B' => 'Binary',
    'C' => 'Compressed',
    'f' => 'Sort Missing First',
    'l' => 'Sort Missing Last',
  ];

  /**
   * An array of Solr schema properties for this field.
   *
   * @var string[]
   */
  protected $schema;

  /**
   * {@inheritdoc}
   */
  public function isList() {
    return $this->isMultivalued();
  }

  /**
   * {@inheritdoc}
   */
  public function isReadOnly() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isComputed() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isRequired() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSchema() {
    if (!isset($this->schema)) {
      foreach (str_split(str_replace('-', '', $this->definition['schema'])) as $key) {
        $this->schema[$key] = self::$schemaLabels[$key] ?? $key;
      }
    }
    return $this->schema;
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicBase() {
    return $this->field['dynamicBase'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isIndexed() {
    $this->getSchema();
    return isset($this->schema['I']);
  }

  /**
   * {@inheritdoc}
   */
  public function isTokenized() {
    $this->getSchema();
    return isset($this->schema['T']);
  }

  /**
   * {@inheritdoc}
   */
  public function isStored() {
    $this->getSchema();
    return isset($this->schema['S']);
  }

  /**
   * {@inheritdoc}
   */
  public function isMultivalued() {
    $this->getSchema();
    return isset($this->schema['M']);
  }

  /**
   * {@inheritdoc}
   */
  public function isTermVectorStored() {
    $this->getSchema();
    return isset($this->schema['V']);
  }

  /**
   * {@inheritdoc}
   */
  public function isStoreOffsetWithTermVector() {
    $this->getSchema();
    return isset($this->schema['o']);
  }

  /**
   * {@inheritdoc}
   */
  public function isStorePositionWithTermVector() {
    $this->getSchema();
    return isset($this->schema['p']);
  }

  /**
   * {@inheritdoc}
   */
  public function isOmitNorms() {
    $this->getSchema();
    return isset($this->schema['O']);
  }

  /**
   * {@inheritdoc}
   */
  public function isLazy() {
    $this->getSchema();
    return isset($this->schema['L']);
  }

  /**
   * {@inheritdoc}
   */
  public function isBinary() {
    $this->getSchema();
    return isset($this->schema['B']);
  }

  /**
   * {@inheritdoc}
   */
  public function isCompressed() {
    $this->getSchema();
    return isset($this->schema['C']);
  }

  /**
   * {@inheritdoc}
   */
  public function isSortMissingFirst() {
    $this->getSchema();
    return isset($this->schema['f']);
  }

  /**
   * {@inheritdoc}
   */
  public function isSortMissingLast() {
    $this->getSchema();
    return isset($this->schema['l']);
  }

  /**
   * {@inheritdoc}
   */
  public function isPossibleKey() {
    return !$this->getDynamicBase()
      && $this->isStored()
      && !$this->isMultivalued();
  }

  /**
   * {@inheritdoc}
   */
  public function isSortable() {
    return $this->isIndexed()
      && !$this->isMultivalued();
  }

  /**
   * {@inheritdoc}
   */
  public function isFulltextSearchable() {
    return $this->isIndexed()
      && $this->isTokenized();
  }

  /**
   * {@inheritdoc}
   */
  public function isFilterable() {
    return $this->isIndexed()
      && !$this->isTokenized();
  }

}
