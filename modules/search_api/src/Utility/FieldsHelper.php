<?php

namespace Drupal\search_api\Utility;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceInterface;
use Drupal\Core\TypedData\ListDataDefinitionInterface;
use Drupal\Core\TypedData\ListInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Item\Item;
use Drupal\search_api\Processor\ConfigurablePropertyInterface;
use Drupal\search_api\Processor\ProcessorInterface;
use Drupal\search_api\Processor\ProcessorPropertyInterface;
use Drupal\search_api\SearchApiException;
use Drupal\text\TextProcessed;
use Symfony\Component\DependencyInjection\Container;

/**
 * Provides helper methods for dealing with Search API fields and properties.
 */
class FieldsHelper implements FieldsHelperInterface {

  /**
   * The theme switcher.
   */
  protected ThemeSwitcherInterface $themeSwitcher;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityTypeBundleInfoInterface $entityBundleInfo,
    protected DataTypeHelperInterface $dataTypeHelper,
    ?ThemeSwitcherInterface $themeSwitcher = NULL,
  ) {
    if (!$themeSwitcher) {
      @trigger_error('Constructing \Drupal\search_api\Utility\FieldsHelper without the $themeSwitcher parameter is deprecated in search_api:8.x-1.31 and it will be required in search_api:2.0.0. See https://www.drupal.org/node/3320841',
        E_USER_DEPRECATED);
      $themeSwitcher = \Drupal::service('search_api.theme_switcher');
    }
    $this->themeSwitcher = $themeSwitcher;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFields(ComplexDataInterface $item, array $fields, $langcode = NULL) {
    // If a language code was given, get the correct translation (if possible).
    if ($langcode) {
      if ($item instanceof TranslatableInterface) {
        if ($item->hasTranslation($langcode)) {
          $item = $item->getTranslation($langcode);
        }
      }
      else {
        $value = $item->getValue();
        if ($value instanceof ContentEntityInterface) {
          if ($value->hasTranslation($langcode)) {
            $item = $value->getTranslation($langcode)->getTypedData();
          }
        }
      }
    }

    // Figure out which fields are directly on the item and which need to be
    // extracted from nested items.
    $directFields = [];
    $nestedFields = [];
    foreach (array_keys($fields) as $key) {
      if (str_contains($key, ':')) {
        [$direct, $nested] = explode(':', $key, 2);
        $nestedFields[$direct][$nested] = $fields[$key];
      }
      else {
        $directFields[] = $key;
      }
    }
    // Extract the direct fields.
    $properties = $item->getProperties(TRUE);
    foreach ($directFields as $key) {
      if (empty($properties[$key])) {
        continue;
      }
      $data = $item->get($key);
      foreach ($fields[$key] as $field) {
        $this->extractField($data, $field);
      }
    }
    // Recurse for all nested fields.
    foreach ($nestedFields as $direct => $fieldsNested) {
      if (empty($properties[$direct])) {
        continue;
      }
      $itemNested = $item->get($direct);
      if ($itemNested instanceof DataReferenceInterface) {
        $itemNested = $itemNested->getTarget();
      }
      if ($itemNested instanceof EntityInterface) {
        $itemNested = $itemNested->getTypedData();
      }
      if ($itemNested instanceof ComplexDataInterface && !$itemNested->isEmpty()) {
        $this->extractFields($itemNested, $fieldsNested, $langcode);
      }
      elseif ($itemNested instanceof ListInterface && !$itemNested->isEmpty()) {
        foreach ($itemNested as $listItem) {
          if ($listItem instanceof ComplexDataInterface && !$listItem->isEmpty()) {
            $this->extractFields($listItem, $fieldsNested, $langcode);
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function extractField(TypedDataInterface $data, FieldInterface $field) {
    // Switch to the default theme for TextProcessed rendering.
    $active_theme = NULL;
    if ($data instanceof TextProcessed) {
      $active_theme = $this->themeSwitcher->switchToDefault();
    }

    $values = $this->extractFieldValues($data);

    foreach ($values as $value) {
      $field->addValue($value);
    }
    $field->setOriginalType($data->getDataDefinition()->getDataType());

    // Restore the original theme if themes got switched before.
    if ($active_theme) {
      $this->themeSwitcher->switchBack($active_theme);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function extractFieldValues(TypedDataInterface $data) {
    $definition = $data->getDataDefinition();

    // Process list data types.
    if ($definition->isList()) {
      $values = [];
      foreach ($data as $piece) {
        $values[] = $this->extractFieldValues($piece);
      }
      return $values ? call_user_func_array('array_merge', $values) : [];
    }

    // Process complex data types.
    if ($definition instanceof ComplexDataDefinitionInterface) {
      $main_property_name = $definition->getMainPropertyName();
      $data_properties = $data->getProperties(TRUE);
      if (isset($data_properties[$main_property_name])) {
        return $this->extractFieldValues($data_properties[$main_property_name]);
      }
      return [];
    }

    // Process simple (scalar) data types.
    $value = $data->getValue();
    if (is_array($value)) {
      return array_values($value);
    }
    return [$value];
  }

  /**
   * {@inheritdoc}
   */
  public function extractItemValues(array $items, array $required_properties, $load = TRUE) {
    $extracted_values = [];

    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $i => $item) {
      $index = $item->getIndex();
      $item_values = [];
      /** @var \Drupal\search_api\Item\FieldInterface[][] $missing_fields */
      $missing_fields = [];
      $processor_fields = [];
      $needed_processors = [];
      foreach ([NULL, $item->getDatasourceId()] as $datasource_id) {
        if (empty($required_properties[$datasource_id])) {
          continue;
        }

        $properties = $index->getPropertyDefinitions($datasource_id);
        foreach ($required_properties[$datasource_id] as $property_path => $combined_id) {
          // Extract a field ID the caller might have added to the property
          // path.
          $field_id = NULL;
          if (strpos($property_path, '|') !== FALSE) {
            [$property_path, $field_id] = explode('|', $property_path, 2);
          }

          $item_values[$combined_id] = [];

          // If a field with the right property path is already set on the item,
          // use it. This might actually make problems in case the values have
          // already been processed in some way, or use a data type that
          // transformed their original value. But that will hopefully not be a
          // problem in most situations.
          // In case of duplicates (for configurable fields, mostly) we prefer
          // the one matching the given $combined_id, since several callers (for
          // instance, the Highlight processor) pass the field ID there.
          $field = $this->findField($item->getFields(FALSE), $datasource_id, $property_path, $combined_id);
          if ($field) {
            $item_values[$combined_id] = $field->getValues();
            continue;
          }

          // There are no values present on the item for this property. If we
          // don't want to extract any fields, skip it.
          if (!$load) {
            continue;
          }

          // If the field is not already on the item, we need to extract it. We
          // set our own combined ID as the field identifier as kind of a hack,
          // to easily be able to add the field values to $property_values
          // afterwards.
          // In case the first part of the property path refers to a
          // processor-defined property, we need to use the processor to
          // retrieve the value. Otherwise, we extract it normally from the
          // data object.
          $property_name = Utility::splitPropertyPath($property_path, FALSE)[0];
          $property = $properties[$property_name] ?? NULL;
          if ($property instanceof ProcessorPropertyInterface) {
            $field_info = [
              'datasource_id' => $datasource_id,
              'property_path' => $property_path,
            ];
            if ($property instanceof ConfigurablePropertyInterface) {
              $field_info['configuration'] = $property->defaultConfiguration();
              // If the index contains a field with that property, just use the
              // configuration from there instead of the default configuration.
              // This will probably be what users expect in most situations.
              // If the caller passed the field ID with the property path, even
              // better.
              if ($field_id && $index->getField($field_id)) {
                $field_info['configuration'] = $index->getField($field_id)
                  ->getConfiguration();
              }
              else {
                $field = $this->findField($index->getFields(), $datasource_id, $property_path, $combined_id);
                if ($field) {
                  $field_info['configuration'] = $field->getConfiguration();
                }
              }
            }
            $processor_fields[] = $this->createField($index, $combined_id, $field_info);
            $needed_processors[$property->getProcessorId()] = TRUE;
          }
          elseif ($datasource_id) {
            $missing_fields[$property_path][] = $this->createField($index, $combined_id);
          }
        }
      }
      if ($missing_fields) {
        $this->extractFields($item->getOriginalObject(), $missing_fields, $item->getLanguage());
        foreach ($missing_fields as $property_fields) {
          foreach ($property_fields as $field) {
            $item_values[$field->getFieldIdentifier()] = $field->getValues();
          }
        }
      }
      if ($processor_fields) {
        $dummy_item = clone $item;
        $dummy_item->setFields($processor_fields);
        $dummy_item->setFieldsExtracted(TRUE);
        $processors = $index->getProcessorsByStage(ProcessorInterface::STAGE_ADD_PROPERTIES);
        foreach ($processors as $processor_id => $processor) {
          if (isset($needed_processors[$processor_id])) {
            $processor->addFieldValues($dummy_item);
          }
        }
        foreach ($processor_fields as $field) {
          $item_values[$field->getFieldIdentifier()] = $field->getValues();
        }
      }

      $extracted_values[$i] = $item_values;
    }

    return $extracted_values;
  }

  /**
   * Finds a field within an array of fields.
   *
   * @param \Drupal\search_api\Item\FieldInterface[] $fields
   *   The fields to search.
   * @param string|null $datasource_id
   *   The datasource ID of the field that should be found.
   * @param string $property_path
   *   The property path of the field that should be found.
   * @param string|null $preferred_field_id
   *   (optional) The preferred field ID: if multiple fields are found matching
   *   the given datasource and property path, but one has this field ID, then
   *   that field is returned. Otherwise, the returned field is undefined.
   *
   * @return \Drupal\search_api\Item\FieldInterface|null
   *   The found field, or NULL if it couldn't be found.
   */
  protected function findField(array $fields, ?string $datasource_id, string $property_path, ?string $preferred_field_id = NULL): ?FieldInterface {
    $return = NULL;
    foreach ($this->filterForPropertyPath($fields, $datasource_id, $property_path) as $field) {
      if ($field->getFieldIdentifier() === $preferred_field_id) {
        return $field;
      }
      elseif (!$return) {
        $return = $field;
      }
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function filterForPropertyPath(array $fields, $datasource_id, $property_path) {
    $found_fields = [];
    foreach ($fields as $field_id => $field) {
      if ($field->getDatasourceId() === $datasource_id && $field->getPropertyPath() === $property_path) {
        $found_fields[$field_id] = $field;
      }
    }
    return $found_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getNestedProperties(ComplexDataDefinitionInterface $property) {
    $nestedProperties = $property->getPropertyDefinitions();
    if ($property instanceof EntityDataDefinitionInterface) {
      $entity_type_id = $property->getEntityTypeId();
      $is_content_type = $this->isContentEntityType($entity_type_id);
      if ($is_content_type) {
        $bundles = $property->getBundles() ?: array_keys($this->entityBundleInfo->getBundleInfo($entity_type_id));
        foreach ($bundles as $bundle) {
          $bundleProperties = $this->entityFieldManager
            ->getFieldDefinitions($entity_type_id, $bundle);
          $nestedProperties += $bundleProperties;
        }
      }
    }
    return $nestedProperties;
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveNestedProperty(array $properties, $propertyPath) {
    [$key, $nestedPath] = Utility::splitPropertyPath($propertyPath, FALSE);
    if (!isset($properties[$key])) {
      return NULL;
    }

    $property = $this->getInnerProperty($properties[$key]);
    if ($nestedPath === NULL) {
      return $property;
    }

    if (!$property instanceof ComplexDataDefinitionInterface) {
      return NULL;
    }

    return $this->retrieveNestedProperty($this->getNestedProperties($property), $nestedPath);
  }

  /**
   * {@inheritdoc}
   */
  public function getInnerProperty(DataDefinitionInterface $property) {
    while ($property instanceof ListDataDefinitionInterface) {
      $property = $property->getItemDefinition();
    }
    while ($property instanceof DataReferenceDefinitionInterface) {
      $property = $property->getTargetDefinition();
    }
    return $property;
  }

  /**
   * {@inheritdoc}
   */
  public function isContentEntityType($entity_type_id) {
    try {
      $definition = $this->entityTypeManager->getDefinition($entity_type_id);
      return $definition->entityClassImplements(ContentEntityInterface::class);
    }
    catch (PluginNotFoundException) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isFieldIdReserved($fieldId) {
    return str_starts_with($fieldId, 'search_api_');
  }

  /**
   * {@inheritdoc}
   */
  public function createItem(IndexInterface $index, $id, ?DatasourceInterface $datasource = NULL) {
    return new Item($index, $id, $datasource);
  }

  /**
   * {@inheritdoc}
   */
  public function createItemFromObject(IndexInterface $index, ComplexDataInterface $originalObject, $id = NULL, ?DatasourceInterface $datasource = NULL) {
    if (!isset($id)) {
      if (!isset($datasource)) {
        throw new \InvalidArgumentException('Need either an item ID or the datasource to create a search item from an object.');
      }

      $item_id = $datasource->getItemId($originalObject);
      if (!$item_id) {
        throw new \InvalidArgumentException('Object does not belong to the datasource.');
      }

      $id = Utility::createCombinedId($datasource->getPluginId(), $item_id);
    }
    $item = $this->createItem($index, $id, $datasource);
    $item->setOriginalObject($originalObject);
    return $item;
  }

  /**
   * {@inheritdoc}
   */
  public function createField(IndexInterface $index, $fieldIdentifier, array $fieldInfo = []) {
    $field = new Field($index, $fieldIdentifier);

    foreach ($fieldInfo as $key => $value) {
      $method = 'set' . Container::camelize($key);
      if (method_exists($field, $method)) {
        $field->$method($value);
      }
    }

    return $field;
  }

  /**
   * {@inheritdoc}
   */
  public function createFieldFromProperty(IndexInterface $index, DataDefinitionInterface $property, $datasourceId, $propertyPath, $fieldId = NULL, $type = NULL) {
    $fieldId = $fieldId ?? $this->getNewFieldId($index, $propertyPath);

    if (!isset($type)) {
      $typeMapping = $this->dataTypeHelper->getFieldTypeMapping();
      $propertyType = $property->getDataType();
      if (isset($typeMapping[$propertyType])) {
        $type = $typeMapping[$propertyType];
      }
      else {
        $propertyName = $property->getLabel();
        throw new SearchApiException("No default data type mapping could be found for property '$propertyName' ($propertyPath) of type '$propertyType'.");
      }
    }

    $fieldInfo = [
      'label' => $property->getLabel(),
      'datasource_id' => $datasourceId,
      'property_path' => $propertyPath,
      'type' => $type,
      'data_definition' => $property,
    ];
    if ($property instanceof ConfigurablePropertyInterface) {
      $fieldInfo['configuration'] = $property->defaultConfiguration();
    }
    return $this->createField($index, $fieldId, $fieldInfo);
  }

  /**
   * {@inheritdoc}
   */
  public function getNewFieldId(IndexInterface $index, $propertyPath) {
    [, $suggestedId] = Utility::splitPropertyPath($propertyPath);

    // Avoid clashes with reserved IDs by removing the reserved "search_api_"
    // from our suggested ID.
    $suggestedId = str_replace('search_api_', '', $suggestedId);

    $fieldId = $suggestedId;
    $i = 0;
    while ($index->getField($fieldId)) {
      $fieldId = $suggestedId . '_' . ++$i;
    }

    while ($this->isFieldIdReserved($fieldId)) {
      $fieldId = '_' . $fieldId;
    }

    return $fieldId;
  }

  /**
   * {@inheritdoc}
   */
  public function compareFieldLabels(FieldInterface $a, FieldInterface $b) {
    return strnatcasecmp((string) $a->getLabel(), (string) $b->getLabel());
  }

}
