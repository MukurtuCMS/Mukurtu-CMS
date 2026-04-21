<?php

namespace Drupal\search_api_solr\TypedData;

use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\search_api\Entity\Index;

/**
 * A typed data definition class for describing Solr documents.
 */
class SolrDocumentDefinition extends ComplexDataDefinitionBase implements SolrDocumentDefinitionInterface {

  /**
   * The Search API server the Solr document definition belongs to.
   *
   * @var \Drupal\search_api\ServerInterface
   */
  protected $server;

  /**
   * Creates a new Solr document definition.
   *
   * @param string $index_id
   *   The Search Api index the Solr document definition belongs to.
   *
   * @return static
   */
  public static function create($index_id = NULL) {
    $definition['type'] = $index_id ? 'solr_document:' . $index_id : 'solr_document';
    $document_definition = new static($definition);
    if ($index_id) {
      $document_definition->setIndexId($index_id);
    }
    return $document_definition;
  }

  /**
   * {@inheritdoc}
   */
  public static function createFromDataType($data_type) {
    // The data type should be in the form of "solr_document:$index_id" or
    // "solr_multisite_document:$index_id".
    $parts = explode(':', $data_type, 2);
    if (!in_array($parts[0], ['solr_document', 'solr_multisite_document'])) {
      throw new \InvalidArgumentException('Data type must be in the form of "solr_document:INDEX_ID" or solr_multisite_document:INDEX_ID.');
    }

    return self::create($parts[1]);
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexId() {
    return $this->definition['constraints']['Index'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setIndexId(string $index_id) {
    return $this->addConstraint('Index', $index_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    if (!isset($this->propertyDefinitions)) {
      $this->propertyDefinitions = [];
      if (!empty($this->getIndexId())) {
        $index = Index::load($this->getIndexId());
        /** @var \Drupal\search_api_solr\SolrFieldManagerInterface $field_manager */
        $field_manager = \Drupal::getContainer()->get('solr_field.manager');
        $this->propertyDefinitions = $field_manager->getFieldDefinitions($index);
      }
    }
    return $this->propertyDefinitions;
  }

}
