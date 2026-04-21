<?php

namespace Drupal\search_api\Item;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\search_api\DataType\DataTypePluginManager;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\Processor\ConfigurablePropertyInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Utility\Utility;

/**
 * Represents a field on a search item that can be indexed.
 */
class Field implements \IteratorAggregate, FieldInterface {

  use LoggerTrait;

  /**
   * The index this field is attached to.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * The ID of the index this field is attached to.
   *
   * This is only used to avoid serialization of the index in __sleep(). If
   * present, the value of this property is used to load the correct index
   * object again in getIndex().
   *
   * @var string
   */
  protected $indexId;

  /**
   * The field's identifier.
   *
   * @var string
   */
  protected $fieldIdentifier;

  /**
   * The field's original identifier.
   *
   * @var string
   */
  protected $originalFieldIdentifier;

  /**
   * The field's datasource's ID.
   *
   * @var string|null
   */
  protected $datasourceId;

  /**
   * The field's datasource.
   *
   * @var \Drupal\search_api\Datasource\DatasourceInterface|null
   */
  protected $datasource;

  /**
   * The property path on the search object.
   *
   * @var string
   */
  protected $propertyPath;

  /**
   * This field's data definition.
   *
   * @var \Drupal\Core\TypedData\DataDefinitionInterface
   */
  protected $dataDefinition;

  /**
   * The human-readable label for this field.
   *
   * @var string|null
   */
  protected $label;

  /**
   * The human-readable description for this field.
   *
   * FALSE if the field has no description.
   *
   * @var string|false
   */
  protected $description;

  /**
   * The human-readable label for this field's datasource.
   *
   * @var string
   */
  protected $labelPrefix;

  /**
   * The Search API data type of this field.
   *
   * @var string
   */
  protected $type;

  /**
   * The boost assigned to this field, if any.
   *
   * @var float
   */
  protected $boost;

  /**
   * Whether this field should be hidden from the user.
   *
   * @var bool
   */
  protected $hidden;

  /**
   * Whether this field should always be enabled/indexed.
   *
   * @var bool
   */
  protected $indexedLocked;

  /**
   * Whether this field type should be locked.
   *
   * @var bool
   */
  protected $typeLocked;

  /**
   * The field's configuration.
   *
   * @var array
   */
  protected $configuration = [];

  /**
   * This field's dependencies, if any.
   *
   * @var string[][]
   */
  protected $dependencies = [];

  /**
   * The field's values.
   *
   * @var array
   */
  protected $values = [];

  /**
   * The original data type of this field.
   *
   * @var string
   */
  protected $originalType;

  /**
   * The data type manager.
   *
   * @var \Drupal\search_api\DataType\DataTypePluginManager|null
   */
  protected $dataTypeManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|null
   */
  protected $entityTypeManager;

  /**
   * Constructs a Field object.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The field's index.
   * @param string $field_identifier
   *   The field's identifier.
   */
  public function __construct(IndexInterface $index, $field_identifier) {
    $this->index = $index;
    $this->fieldIdentifier = $this->originalFieldIdentifier = $field_identifier;
  }

  /**
   * Retrieves the data type manager.
   *
   * @return \Drupal\search_api\DataType\DataTypePluginManager
   *   The data type manager.
   */
  public function getDataTypeManager() {
    return $this->dataTypeManager ?: \Drupal::service('plugin.manager.search_api.data_type');
  }

  /**
   * Sets the data type manager.
   *
   * @param \Drupal\search_api\DataType\DataTypePluginManager $data_type_manager
   *   The new data type manager.
   *
   * @return $this
   */
  public function setDataTypeManager(DataTypePluginManager $data_type_manager) {
    $this->dataTypeManager = $data_type_manager;
    return $this;
  }

  /**
   * Retrieves the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  public function getEntityTypeManager(): EntityTypeManagerInterface {
    return $this->entityTypeManager ?: \Drupal::entityTypeManager();
  }

  /**
   * Sets the entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The new entity type manager.
   *
   * @return $this
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager): self {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndex() {
    if (!$this->index && $this->indexId !== NULL) {
      $this->index = $this->getEntityTypeManager()
        ->getStorage('search_api_index')
        ->load($this->indexId);
      $this->indexId = NULL;
    }
    return $this->index;
  }

  /**
   * {@inheritdoc}
   */
  public function setIndex(IndexInterface $index) {
    $current_index_id = $this->index ? $this->index->id() : $this->indexId;
    if ($current_index_id !== NULL && $current_index_id != $index->id()) {
      throw new \InvalidArgumentException('Attempted to change the index of a field object.');
    }
    $this->index = $index;
    $this->indexId = NULL;
    $this->datasource = NULL;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldIdentifier() {
    return $this->fieldIdentifier;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalFieldIdentifier() {
    return $this->originalFieldIdentifier;
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldIdentifier($field_id) {
    $this->fieldIdentifier = $field_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function wasRenamed() {
    return $this->fieldIdentifier != $this->originalFieldIdentifier;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings() {
    $settings = [
      'label' => $this->getLabel(),
      'datasource_id' => $this->getDatasourceId(),
      'property_path' => $this->getPropertyPath(),
      'type' => $this->getType(),
    ];
    if ($this->getDatasourceId() === NULL) {
      unset($settings['datasource_id']);
    }
    if ($this->getBoost() != 1.0) {
      $settings['boost'] = $this->getBoost();
    }
    if ($this->isIndexedLocked()) {
      $settings['indexed_locked'] = TRUE;
    }
    if ($this->isTypeLocked()) {
      $settings['type_locked'] = TRUE;
    }
    if ($this->isHidden()) {
      $settings['hidden'] = TRUE;
    }
    if ($this->getConfiguration()) {
      $settings['configuration'] = $this->getConfiguration();
    }
    if ($this->getDependencies()) {
      $settings['dependencies'] = $this->getDependencies();
    }
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getDatasourceId() {
    return $this->datasourceId;
  }

  /**
   * {@inheritdoc}
   */
  public function getDatasource() {
    if (!isset($this->datasource) && isset($this->datasourceId)) {
      $this->datasource = $this->getIndex()->getDatasource($this->datasourceId);
    }
    return $this->datasource;
  }

  /**
   * {@inheritdoc}
   */
  public function setDatasourceId($datasource_id) {
    if ($this->datasourceId != $datasource_id) {
      $this->datasource = NULL;
    }
    $this->datasourceId = $datasource_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyPath() {
    return $this->propertyPath;
  }

  /**
   * {@inheritdoc}
   */
  public function setPropertyPath($property_path) {
    $this->propertyPath = $property_path;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCombinedPropertyPath() {
    return Utility::createCombinedId($this->getDatasourceId(), $this->getPropertyPath());
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel($label) {
    $this->label = $label;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    if (!isset($this->description)) {
      try {
        $property = $this->getDataDefinition();
        if ($property instanceof ConfigurablePropertyInterface) {
          $this->description = $property->getFieldDescription($this);
        }
        else {
          $this->description = $property->getDescription();
        }
        $this->description = $this->description ?: FALSE;
      }
      catch (SearchApiException $e) {
        $this->logException($e);
      }
    }
    return $this->description ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription($description) {
    // Set FALSE instead of NULL so caching will work properly.
    $this->description = $description ?: FALSE;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrefixedLabel() {
    if (!isset($this->labelPrefix)) {
      $this->labelPrefix = '';
      if (isset($this->datasourceId)) {
        $this->labelPrefix = $this->datasourceId;
        try {
          $this->labelPrefix = $this->getDatasource()->label();
        }
        catch (SearchApiException $e) {
          $this->logException($e);
        }
        $this->labelPrefix .= ' » ';
      }
    }
    return $this->labelPrefix . $this->getLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function setLabelPrefix($label_prefix) {
    $this->labelPrefix = $label_prefix;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isHidden() {
    return (bool) $this->hidden;
  }

  /**
   * {@inheritdoc}
   */
  public function setHidden($hidden = TRUE) {
    $this->hidden = $hidden;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDataDefinition() {
    if (!isset($this->dataDefinition)) {
      $definitions = $this->getIndex()
        ->getPropertyDefinitions($this->getDatasourceId());
      if ($this->getPropertyPath() === NULL) {
        $field_label = $this->getLabel() ?: $this->getFieldIdentifier();
        $index_label = $this->getIndex()->label();
        throw new SearchApiException("Field '$field_label' on index '$index_label' has no property path set.");
      }
      $definition = \Drupal::getContainer()
        ->get('search_api.fields_helper')
        ->retrieveNestedProperty($definitions, $this->getPropertyPath());
      if (!$definition) {
        $field_label = $this->getLabel() ?: $this->getFieldIdentifier();
        $index_label = $this->getIndex()->label();
        throw new SearchApiException("Could not retrieve data definition for field '$field_label' on index '$index_label'.");
      }
      elseif ($definition instanceof ConfigurablePropertyInterface) {
        $definition = clone $definition;
        $definition->setConfiguration($this->configuration);
      }
      $this->dataDefinition = $definition;
    }
    return $this->dataDefinition;
  }

  /**
   * Sets the field's data definition.
   *
   * This should mainly be used only when creating a new field object. Calling
   * this on an existing field object might not work as expected.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $data_definition
   *   The field's new data definition.
   *
   * @return $this
   */
  public function setDataDefinition(DataDefinitionInterface $data_definition) {
    $this->dataDefinition = $data_definition;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->type;
  }

  /**
   * {@inheritdoc}
   */
  public function getDataTypePlugin() {
    $data_type_manager = $this->getDataTypeManager();
    if ($data_type_manager->hasDefinition($this->getType())) {
      return $data_type_manager->createInstance($this->getType());
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setType($type) {
    if ($type != $this->type && $this->isTypeLocked()) {
      $field_label = $this->getLabel();
      $index_label = $this->getIndex()->label();
      throw new SearchApiException("Trying to change the type of field '$field_label' on index '$index_label', which is locked.");
    }
    $this->type = $type;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getValues() {
    return $this->values;
  }

  /**
   * {@inheritdoc}
   */
  public function setValues(array $values) {
    $this->values = array_values($values);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addValue($value) {
    // The data type has to be able to alter the given value before it is
    // included.
    $data_type_plugin = $this->getDataTypePlugin();
    if ($data_type_plugin) {
      $value = $data_type_plugin->getValue($value);
    }

    $this->values[] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalType() {
    if (!isset($this->originalType)) {
      $this->originalType = 'string';
      try {
        $this->originalType = $this->getDataDefinition()->getDataType();
      }
      catch (SearchApiException $e) {
        $this->logException($e);
      }
    }
    return $this->originalType;
  }

  /**
   * {@inheritdoc}
   */
  public function setOriginalType($original_type) {
    $this->originalType = $original_type;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBoost() {
    return $this->boost ?? 1.0;
  }

  /**
   * {@inheritdoc}
   */
  public function setBoost($boost) {
    $this->boost = (float) $boost;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isIndexedLocked() {
    return (bool) $this->indexedLocked;
  }

  /**
   * {@inheritdoc}
   */
  public function setIndexedLocked($indexed_locked = TRUE) {
    $this->indexedLocked = $indexed_locked;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isTypeLocked() {
    return (bool) $this->typeLocked;
  }

  /**
   * {@inheritdoc}
   */
  public function setTypeLocked($type_locked = TRUE) {
    $this->typeLocked = $type_locked;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDependencies() {
    return $this->dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function setDependencies(array $dependencies) {
    $this->dependencies = $dependencies;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator(): \Traversable {
    return new \ArrayIterator($this->values);
  }

  /**
   * Implements the magic __toString() method to simplify debugging.
   */
  public function __toString() {
    $label = $this->getLabel();
    $field_id = $this->getFieldIdentifier();
    $type = $this->getType();
    $out = "$label [$field_id]: indexed as type $type";
    $is_text_type = \Drupal::getContainer()
      ->get('search_api.data_type_helper')
      ->isTextType($type);
    if ($is_text_type) {
      $out .= ' (boost ' . $this->getBoost() . ')';
    }
    if ($this->getValues()) {
      $out .= "\nValues:";
      foreach ($this->getValues() as $value) {
        $value = str_replace("\n", "\n  ", "$value");
        $out .= "\n- " . $value;
      }
    }
    return $out;
  }

  /**
   * Implements the magic __sleep() method to control object serialization.
   */
  public function __sleep() {
    if ($this->index) {
      $this->indexId = $this->index->id();
    }
    $properties = get_object_vars($this);
    // Don't serialize the index or other objects that can easily be loaded
    // again.
    unset($properties['index']);
    unset($properties['datasource']);
    unset($properties['dataDefinition']);
    unset($properties['dataTypeManager']);
    return array_keys($properties);
  }

}
