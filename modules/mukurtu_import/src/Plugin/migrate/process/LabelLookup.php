<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * For list text fields (fields of type 'list_string'), do a lookup for machine
 * name based on label.
 *
 * Accepts three mandatory config values: entity type, field name, and bundle.
 *
 * @code
 *  plugin: label_lookup
 *  source: list_text
 *  entity_type: entity_type
 *  field_name: field_name
 *  bundle: bundle
 *
 * @endcode
 */
#[MigrateProcess('label_lookup')]
class LabelLookup extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a LabelLookup object.
   *
   * @param array $configuration
   *    A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *    The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *    The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected EntityFieldManagerInterface $entityFieldManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_field.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $entity_type = $this->configuration['entity_type'];
    $field_name = $this->configuration['field_name'];
    $bundle = $this->configuration['bundle'];

    // Trim whitespace from the value.
    $value = trim((string) $value);

    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
    $field_config = $fields[$field_name];
    if ($field_config instanceof FieldDefinitionInterface) {
      $allowed_values = $field_config->getSetting('allowed_values');
      if (isset($allowed_values[$value])) {
        return $value;
      }

      // Try with a trailing slash for URL-based list values.
      $value_with_slash = rtrim($value, '/') . '/';
      if ($value_with_slash !== $value && isset($allowed_values[$value_with_slash])) {
        return $value_with_slash;
      }

      // Strip markup and normalize whitespace from labels before comparing,
      // so plain text input like "In Copyright" matches labels that contain
      // HTML (e.g., <img> and <a> tags).
      $stripped_values = array_map(fn ($label) => mb_strtolower(trim(preg_replace('/\s+/', ' ', strip_tags((string) $label)))), $allowed_values);
      $normalized_input = mb_strtolower(trim(preg_replace('/\s+/', ' ', $value)));
      if ($key = array_search($normalized_input, $stripped_values)) {
        return $key;
      }
    }

    return $value;
  }

}
