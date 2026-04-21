<?php

declare(strict_types=1);

namespace Drupal\geocoder_field;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\geocoder_field\Annotation\GeocoderField;

/**
 * The Geocoder Field Plugin manager.
 */
class GeocoderFieldPluginManager extends DefaultPluginManager {

  /**
   * The geocoder field preprocessor plugin manager service.
   *
   * @var \Drupal\geocoder_field\PreprocessorPluginManager
   */
  protected $preprocessorPluginManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a new geocoder field plugin manager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations,.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\geocoder_field\PreprocessorPluginManager $preprocessor_plugin_manager
   *   The geocoder field preprocessor plugin manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    PreprocessorPluginManager $preprocessor_plugin_manager,
    EntityFieldManagerInterface $entity_field_manager,
  ) {
    parent::__construct('Plugin/Geocoder/Field', $namespaces, $module_handler, GeocoderFieldPluginInterface::class, GeocoderField::class);
    $this->alterInfo('geocode_field_info');
    $this->setCacheBackend($cache_backend, 'geocode_field_plugins');

    $this->preprocessorPluginManager = $preprocessor_plugin_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * Get Fields Options.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   * @param string $field_name
   *   The field name.
   * @param array $field_types
   *   The field types array.
   *
   * @return mixed
   *   The options results.
   */
  private function getFieldsOptions($entity_type_id, $bundle, $field_name, array $field_types) {
    $options = [];

    foreach ($this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle) as $id => $definition) {
      if (in_array($definition->getType(), $field_types)
        && ($definition->getName()) !== $field_name
        && !in_array($id, ['title', 'revision_log'])) {
        $options[$id] = new TranslatableMarkup(
          '@label (@name) [@type]', [
            '@label' => $definition->getLabel(),
            '@name' => $definition->getName(),
            '@type' => $definition->getType(),
          ]);
      }
    }

    return $options;
  }

  /**
   * Returns the first plugin that handles a specific field type.
   *
   * @param string $field_type
   *   The type of field for which to find a plugin.
   *
   * @return \Drupal\geocoder_field\GeocoderFieldPluginInterface|null
   *   The plugin instance or NULL, if no plugin handles this field type.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getPluginByFieldType($field_type) {
    foreach ($this->getDefinitions() as $definition) {
      if (in_array($field_type, $definition['field_types'])) {
        /** @var \Drupal\geocoder_field\GeocoderFieldPluginInterface $geocoder_field_plugin */
        $geocoder_field_plugin = $this->createInstance($definition['id']);
        return $geocoder_field_plugin;
      }
    }

    return NULL;
  }

  /**
   * Returns the field types that are supported by the Geocoder fields.
   *
   * @return array
   *   An associative array of field type IDs, keyed by field type ID.
   */
  public function getFieldTypes(): array {
    $field_types = [];
    foreach ($this->getDefinitions() as $definition) {
      foreach ($definition['field_types'] as $field_type) {
        $field_types[$field_type] = $field_type;
      }
    }
    return $field_types;
  }

  /**
   * Gets a list of fields available as source for Geocode operations.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   * @param string $field_name
   *   The field name.
   *
   * @return array
   *   The array of source fields and their label.
   */
  public function getGeocodeSourceFields($entity_type_id, $bundle, $field_name) {

    // List the possible Geocoding Field Types.
    $source_fields_types = $this->preprocessorPluginManager->getGeocodeSourceFieldsTypes();

    // Add File and Image field types, for File provider integration.
    if ($this->moduleHandler->moduleExists('image')) {
      array_push($source_fields_types,
        "file",
        "image"
      );
    }

    // Add Address and Country Field types, for Address module integration.
    if ($this->moduleHandler->moduleExists('geocoder_address')) {
      array_push($source_fields_types,
        "address",
        "address_country"
      );
    }

    // Add Computed field types, for Computed field module integration.
    if ($this->moduleHandler->moduleExists('computed_field')) {
      array_push($source_fields_types,
        "computed_string",
        "computed_string_long"
      );

    }

    // Allow other modules to add/alter list of possible Geocoding Field Types.
    $this->moduleHandler->alter('geocode_source_fields', $source_fields_types);

    return $this->getFieldsOptions(
      $entity_type_id,
      $bundle,
      $field_name,
      $source_fields_types
    );
  }

  /**
   * Gets a list of fields available as source for Reverse Geocode operations.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   * @param string $field_name
   *   The field name.
   *
   * @return array
   *   The array of source fields and their label.
   */
  public function getReverseGeocodeSourceFields($entity_type_id, $bundle, $field_name) {

    // List the possible Reverse Geocoding Field Types.
    $source_fields_types = $this->preprocessorPluginManager->getReverseGeocodeSourceFieldsTypes();

    // Allow other modules to add/alter list of possible Reverse
    // Geocoding Field Types.
    $this->moduleHandler->alter('reverse_geocode_source_fields', $source_fields_types);

    return $this->getFieldsOptions(
      $entity_type_id,
      $bundle,
      $field_name,
      $source_fields_types
    );
  }

}
