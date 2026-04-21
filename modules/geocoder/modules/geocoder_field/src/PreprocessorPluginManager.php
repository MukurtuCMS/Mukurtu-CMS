<?php

namespace Drupal\geocoder_field;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\geocoder\GeocoderPluginManagerBase;
use Drupal\geocoder_field\Annotation\GeocoderPreprocessor;

/**
 * Provides a plugin manager for geocoder data preprocessors.
 */
class PreprocessorPluginManager extends GeocoderPluginManagerBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/Geocoder/Preprocessor', $namespaces, $module_handler, PreprocessorInterface::class, GeocoderPreprocessor::class);
    $this->alterInfo('geocoder_preprocessor_info');
    $this->setCacheBackend($cache_backend, 'geocoder_preprocessor_plugins');
  }

  /**
   * Pre-processes a field, running all plugins that support that field type.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field item list to be processed.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public function preprocess(FieldItemListInterface &$field) {
    $type = $field->getFieldDefinition()->getType();

    // Get a list of plugins that are supporting fields of type $type.
    $definitions = array_filter($this->getDefinitions(),
      function ($definition) use ($type) {
        return in_array($type, $definition['field_types']);
      }
    );

    // Sort definitions by 'weight'.
    uasort($definitions, [SortArray::class, 'sortByWeightElement']);

    foreach ($definitions as $plugin_id => $definition) {
      /** @var \Drupal\geocoder_field\PreprocessorInterface $preprocessor */
      $preprocessor = $this->createInstance($plugin_id);
      $preprocessor->setField($field)->preprocess();
    }
  }

  /**
   * Get the ordered list of fields to be Geocoded | Reverse Geocoded.
   *
   * Reorders the fields based on the user-defined GeocoderField weights.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The Entity that needs to be preprocessed.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface[]
   *   An array of field item lists implementing, keyed by field name.
   */
  public function getOrderedGeocodeFields(ContentEntityInterface $entity) {
    $geocoder_fields = [];
    $results = [];

    foreach ($entity->getFields() as $field_name => $field) {
      /** @var \Drupal\Core\Field\FieldConfigInterface $field_config */
      if (!($field_config = $field->getFieldDefinition()) instanceof FieldConfigInterface) {
        // Only configurable fields are subject of geocoding.
        continue;
      }
      $geocoder = $field_config->getThirdPartySettings('geocoder_field');
      if (empty($geocoder['method']) || $geocoder['method'] === 'none') {
        // This field was not configured to geocode/reverse_geocode from/to
        // other field.
        continue;
      }

      $geocoder_fields[$field_name] = [
        'field_name' => $field_name,
        'field_value' => $field,
        'weight' => $geocoder['weight'] ?? 0,
      ];
    }

    usort($geocoder_fields, function ($a, $b) {
      if ($a['weight'] === $b['weight']) {
        return 0;
      }
      return ($a['weight'] < $b['weight']) ? -1 : 1;
    });

    foreach ($geocoder_fields as $field) {
      $results[$field['field_name']] = $field['field_value'];
    }

    return $results;

  }

  /**
   * Check if the source and the original fields are the same.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $source_field
   *   The Source Field.
   * @param \Drupal\Core\Field\FieldItemListInterface $original_field
   *   The Original Field.
   *
   * @return bool
   *   The check result.
   */
  public function sourceFieldIsSameOfOriginal(FieldItemListInterface $source_field, FieldItemListInterface $original_field) {
    $source_value = $source_field->getValue();
    $original_value = $original_field->getValue();

    if (isset($source_value[0]) && !isset($source_value[0]['value']) && isset($source_value[0]['target_id'])) {
      foreach ($source_value as $i => $value) {
        $source_value[$i] = $value['target_id'] ?? '';
      }
    }
    if (isset($original_value[0]) && !isset($original_value[0]['value']) && isset($original_value[0]['target_id'])) {
      foreach ($original_value as $i => $value) {
        $original_value[$i] = $value['target_id'] ?? '';
      }
    }

    return $source_value == $original_value;
  }

}
