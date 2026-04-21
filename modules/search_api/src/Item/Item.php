<?php

namespace Drupal\search_api\Item;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\Processor\ProcessorInterface;
use Drupal\search_api\Processor\ProcessorPropertyInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Utility\Utility;

/**
 * Provides a default implementation for a search item.
 */
class Item implements \IteratorAggregate, ItemInterface {

  use LoggerTrait;

  /**
   * The search index with which this item is associated.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * The ID of the index with which this item is associated.
   *
   * This is only used to avoid serialization of the index in __sleep() and
   * __wakeup().
   *
   * @var string
   */
  protected $indexId;

  /**
   * The ID of this item.
   *
   * @var string
   */
  protected $itemId;

  /**
   * The complex data item this Search API item is based on.
   *
   * @var \Drupal\Core\TypedData\ComplexDataInterface
   */
  protected $originalObject;

  /**
   * The ID of this item's datasource.
   *
   * @var string
   */
  protected $datasourceId;

  /**
   * The datasource of this item.
   *
   * @var \Drupal\search_api\Datasource\DatasourceInterface
   */
  protected $datasource;

  /**
   * The language code of this item.
   *
   * @var string
   */
  protected $language;

  /**
   * The extracted fields of this item.
   *
   * @var \Drupal\search_api\Item\FieldInterface[]
   */
  protected $fields = [];

  /**
   * Whether the fields were already extracted for this item.
   *
   * @var bool
   */
  protected $fieldsExtracted = FALSE;

  /**
   * The HTML text with highlighted text-parts that match the query.
   *
   * @var string
   */
  protected $excerpt;

  /**
   * The score this item had as a result in a corresponding search query.
   *
   * @var float
   */
  protected $score = 1.0;

  /**
   * The boost of this item at indexing time.
   *
   * @var float
   */
  protected $boost = 1.0;

  /**
   * Extra data set on this item.
   *
   * @var array
   */
  protected $extraData = [];

  /**
   * All warnings added to this item.
   *
   * @var string[]|\Drupal\Component\Render\MarkupInterface[]
   */
  protected array $warnings = [];

  /**
   * Cached access results for the item, keyed by user ID.
   *
   * @var \Drupal\Core\Access\AccessResultInterface[]
   *
   * @see getAccessResult()
   */
  protected $accessResults = [];

  /**
   * Constructs an Item object.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The item's search index.
   * @param string $id
   *   The ID of this item.
   * @param \Drupal\search_api\Datasource\DatasourceInterface|null $datasource
   *   (optional) The datasource of this item. If not set, it will be determined
   *   from the ID and loaded from the index.
   */
  public function __construct(IndexInterface $index, $id, ?DatasourceInterface $datasource = NULL) {
    $this->index = $index;
    $this->itemId = $id;
    if ($datasource) {
      $this->datasource = $datasource;
      $this->datasourceId = $datasource->getPluginId();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDatasourceId() {
    if (!isset($this->datasourceId)) {
      [$this->datasourceId] = Utility::splitCombinedId($this->itemId);
    }
    return $this->datasourceId;
  }

  /**
   * {@inheritdoc}
   */
  public function getDatasource() {
    if (!isset($this->datasource)) {
      $this->datasource = $this->index->getDatasource($this->getDatasourceId());
    }
    return $this->datasource;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndex() {
    return $this->index;
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguage() {
    if (!isset($this->language)) {
      $this->language = $this->getDatasource()->getItemLanguage($this->getOriginalObject());
    }
    return $this->language;
  }

  /**
   * {@inheritdoc}
   */
  public function setLanguage($language) {
    $this->language = $language;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->itemId;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalObject($load = TRUE) {
    if (!isset($this->originalObject) && $load) {
      $this->originalObject = $this->index->loadItem($this->itemId);
      if (!$this->originalObject) {
        throw new SearchApiException('Failed to load original object ' . $this->itemId);
      }
    }
    return $this->originalObject;
  }

  /**
   * {@inheritdoc}
   */
  public function setOriginalObject(ComplexDataInterface $original_object) {
    $this->originalObject = $original_object;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getField($field_id, $extract = TRUE) {
    if (isset($this->fields[$field_id])) {
      return $this->fields[$field_id];
    }
    return $this->getFields($extract)[$field_id] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFields($extract = TRUE) {
    if ($extract && !$this->fieldsExtracted) {
      $data_type_fallback_mapping = \Drupal::getContainer()
        ->get('search_api.data_type_helper')
        ->getDataTypeFallbackMapping($this->index);
      $fields_by_property_path = [];
      $processors_with_fields = [];
      foreach ([NULL, $this->getDatasourceId()] as $datasource_id) {
        $properties = $this->index->getPropertyDefinitions($datasource_id);
        foreach ($this->index->getFieldsByDatasource($datasource_id) as $field_id => $field) {
          // Don't overwrite fields that were previously set.
          if (empty($this->fields[$field_id])) {
            $this->fields[$field_id] = clone $field;

            $field_data_type = $this->fields[$field_id]->getType();
            // If the field data type is in the fallback mapping list, then use
            // the fallback type as field type.
            if (isset($data_type_fallback_mapping[$field_data_type])) {
              $this->fields[$field_id]->setType($data_type_fallback_mapping[$field_data_type]);
            }

            // For determining whether the field is provided via a processor, we
            // need to check using the first part of its property path (in other
            // words, the property that's directly on the result item, not
            // nested), since only direct properties of the item can be added by
            // the processor.
            $property = NULL;
            $property_name = Utility::splitPropertyPath($field->getPropertyPath(), FALSE)[0];
            if (isset($properties[$property_name])) {
              $property = $properties[$property_name];
            }
            if ($property instanceof ProcessorPropertyInterface) {
              $processors_with_fields[$property->getProcessorId()] = TRUE;
            }
            elseif ($datasource_id) {
              $fields_by_property_path[$field->getPropertyPath()][] = $this->fields[$field_id];
            }
          }
        }
      }

      // Extract the "regular" properties from the Typed Data and then let all
      // necessary processors add their field values.
      try {
        if ($fields_by_property_path) {
          \Drupal::getContainer()
            ->get('search_api.fields_helper')
            ->extractFields($this->getOriginalObject(), $fields_by_property_path, $this->getLanguage());
        }
        if ($processors_with_fields) {
          $processors = $this->index->getProcessorsByStage(ProcessorInterface::STAGE_ADD_PROPERTIES);
          foreach (array_intersect_key($processors, $processors_with_fields) as $processor) {
            $processor->addFieldValues($this);
          }
        }
      }
      catch (SearchApiException $e) {
        // If we couldn't load the object, just log an error and fail
        // silently to set the values.
        $this->logException($e);
      }

      $this->fieldsExtracted = TRUE;
    }
    return $this->fields;
  }

  /**
   * {@inheritdoc}
   */
  public function setField($field_id, ?FieldInterface $field = NULL) {
    if ($field) {
      if ($field->getFieldIdentifier() !== $field_id) {
        throw new \InvalidArgumentException('The field identifier passed must be consistent with the identifier set on the field object.');
      }
      // Make sure that the field has the same index object set as we. This
      // might otherwise cause impossibly hard-to-detect bugs.
      $field->setIndex($this->index);
      $this->fields[$field_id] = $field;
    }
    else {
      unset($this->fields[$field_id]);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setFields(array $fields) {
    // Make sure that all fields have the same index object set as we. This
    // might otherwise cause impossibly hard-to-detect bugs.
    /** @var \Drupal\search_api\Item\FieldInterface $field */
    foreach ($fields as $field) {
      $field->setIndex($this->index);
    }
    $this->fields = $fields;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isFieldsExtracted() {
    return $this->fieldsExtracted;
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldsExtracted($fields_extracted) {
    $this->fieldsExtracted = $fields_extracted;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getScore() {
    return $this->score;
  }

  /**
   * {@inheritdoc}
   */
  public function setScore($score) {
    if (!is_numeric($score) || ((float) $score) < 0) {
      @trigger_error('Passing negative numbers or non-numeric values to \Drupal\search_api\Item\Item::setScore() is deprecated in search_api:8.x-1.36 and will stop working in search_api:2.0.0. See https://www.drupal.org/node/3485262', E_USER_DEPRECATED);
    }
    $this->score = (float) $score;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBoost() {
    return $this->boost;
  }

  /**
   * {@inheritdoc}
   */
  public function setBoost($boost) {
    if (!is_numeric($boost) || ((float) $boost) < 0) {
      @trigger_error('Passing negative numbers or non-numeric values to \Drupal\search_api\Item\Item::setBoost() is deprecated in search_api:8.x-1.36 and will stop working in search_api:2.0.0. See https://www.drupal.org/node/3485262', E_USER_DEPRECATED);
    }
    $this->boost = (float) $boost;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExcerpt() {
    return $this->excerpt;
  }

  /**
   * {@inheritdoc}
   */
  public function setExcerpt($excerpt) {
    $this->excerpt = $excerpt;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasExtraData($key) {
    return array_key_exists($key, $this->extraData);
  }

  /**
   * {@inheritdoc}
   */
  public function getExtraData($key, $default = NULL) {
    return array_key_exists($key, $this->extraData) ? $this->extraData[$key] : $default;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllExtraData() {
    return $this->extraData;
  }

  /**
   * {@inheritdoc}
   */
  public function setExtraData($key, $data = NULL) {
    if (isset($data)) {
      $this->extraData[$key] = $data;
    }
    else {
      unset($this->extraData[$key]);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasWarnings(): bool {
    return (bool) $this->warnings;
  }

  /**
   * {@inheritdoc}
   */
  public function getWarnings(): array {
    return $this->warnings;
  }

  /**
   * {@inheritdoc}
   */
  public function addWarning(MarkupInterface|string $warning): static {
    $this->warnings[] = $warning;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(?AccountInterface $account = NULL) {
    @trigger_error('\Drupal\search_api\Item\ItemInterface::checkAccess() is deprecated in search_api:8.x-1.14 and is removed from search_api:2.0.0. Use getAccessResult() instead. See https://www.drupal.org/node/3051902', E_USER_DEPRECATED);
    return $this->getAccessResult($account)->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessResult(?AccountInterface $account = NULL) {
    if (!$account) {
      $account = \Drupal::currentUser();
    }
    $uid = $account->id();

    if (empty($this->accessResults[$uid])) {
      try {
        $this->accessResults[$uid] = $this->getDatasource()
          ->getItemAccessResult($this->getOriginalObject(), $account);
      }
      catch (SearchApiException) {
        $this->accessResults[$uid] = AccessResult::neutral('Item could not be loaded, so cannot check access');
      }
    }

    return $this->accessResults[$uid];
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
  public function getIterator(): \Traversable {
    return new \ArrayIterator($this->getFields());
  }

  /**
   * Implements the magic __clone() method to implement a deep clone.
   */
  public function __clone() {
    // The fields definitely need to be cloned. For the extra data its hard (or,
    // rather, impossible) to tell, but we opt for cloning objects there, too,
    // to be on the (hopefully) safer side. (Ideas for later: introduce an
    // interface that tells us to not clone the data object; or check whether
    // its an entity; or introduce some other system to override this default.)
    foreach ($this->fields as $field_id => $field) {
      $this->fields[$field_id] = clone $field;
    }
    foreach ($this->extraData as $key => $data) {
      if (is_object($data)) {
        $this->extraData[$key] = clone $data;
      }
    }
  }

  /**
   * Implements the magic __sleep() method to avoid serializing the index.
   */
  public function __sleep() {
    $this->indexId = $this->index->id();
    $properties = get_object_vars($this);
    // Don't serialize objects that can easily be loaded again. (We cannot be
    // sure about the "original object", so we do serialize that.
    unset($properties['index']);
    unset($properties['datasource']);
    unset($properties['accessResults']);
    return array_keys($properties);
  }

  /**
   * Implements the magic __wakeup() method to control object unserialization.
   */
  public function __wakeup() {
    // Make sure we have a container to do this. Otherwise, there could be
    // errors when displaying failed tests.
    if ($this->indexId && \Drupal::hasContainer()) {
      $this->index = \Drupal::entityTypeManager()
        ->getStorage('search_api_index')
        ->load($this->indexId);
      $this->indexId = NULL;
      if ($this->index && $this->fields) {
        foreach ($this->fields as $field) {
          $field->setIndex($this->index);
        }
      }
    }
  }

  /**
   * Implements the magic __toString() method to simplify debugging.
   */
  public function __toString() {
    $out = 'Item ' . $this->getId();
    if ($this->getScore() != 1) {
      $out .= "\nScore: " . $this->getScore();
    }
    if ($this->getBoost() != 1) {
      $out .= "\nBoost: " . $this->getBoost();
    }
    if ($this->getExcerpt()) {
      $excerpt = str_replace("\n", "\n  ", $this->getExcerpt());
      $out .= "\nExcerpt: $excerpt";
    }
    if ($this->getFields(FALSE)) {
      $out .= "\nFields:";
      foreach ($this->getFields(FALSE) as $field) {
        $field = str_replace("\n", "\n  ", "$field");
        $out .= "\n- " . $field;
      }
    }
    if ($this->getAllExtraData()) {
      $data = str_replace("\n", "\n  ", print_r($this->getAllExtraData(), TRUE));
      $out .= "\nExtra data: " . $data;
    }
    return $out;
  }

}
