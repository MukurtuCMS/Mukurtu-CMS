<?php

namespace Drupal\geolocation\Plugin\views\argument;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Argument handler for geolocation.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("geolocation_entity_argument")
 */
class EntityArgument extends ProximityArgument implements ContainerFactoryPluginInterface {

  /**
   * The EntityTypeManager object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The EntityFieldManager object.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a Handler object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Geocoder manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The Geocoder manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['geolocation_entity_argument_source'] = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $bundle_info = \Drupal::service("entity_type.bundle.info")->getAllBundleInfo();

    unset($form['description']);

    $options = [];

    foreach ($this->entityFieldManager->getFieldMapByFieldType('geolocation') as $entity_type => $fields) {
      $entity_type_definition = $this->entityTypeManager->getDefinition($entity_type);
      foreach ($fields as $field_name => $field) {
        foreach ($field['bundles'] as $bundle) {
          $bundle_label = empty($bundle_info[$entity_type][$bundle]['label']) ? $entity_type_definition->getBundleLabel() : $bundle_info[$entity_type][$bundle]['label'];
          $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
          $options[$entity_type . ':' . $bundle . ':' . $field_name] = $entity_type_definition->getLabel() . ' - ' . $bundle_label . ' - ' . $field_definitions[$field_name]->getLabel();
        }
      }
    }

    $form['geolocation_entity_argument_source'] = [
      '#type' => 'select',
      '#title' => $this->t('Geolocation Entity Argument Source'),
      '#options' => $options,
      '#weight' => -10,
      '#default_value' => $this->options['geolocation_entity_argument_source'],
      '#description' => $this->t('Format should be in the following format: <strong>"654<=5mi"</strong> (defaults to km). Alternatively, just a valid entity ID, for use as reference location in other fields.'),
    ];
  }

  /**
   * Get coordinates from entity ID.
   *
   * @param int $entity_id
   *   Entity ID.
   *
   * @return array|false
   *   Coordinates.
   */
  protected function getCoordinatesFromEntityId(int $entity_id) {
    if (empty($this->options['geolocation_entity_argument_source'])) {
      return FALSE;
    }

    $values = [];

    $source_parts = explode(':', $this->options['geolocation_entity_argument_source']);
    $entity_type = $source_parts[0];
    $field_name = $source_parts[2];
    if (
      empty($entity_type)
      || empty($field_name)
    ) {
      return FALSE;
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if (empty($entity)) {
      return FALSE;
    }

    $field = $entity->get($field_name);
    if (
      empty($field)
      || $field->isEmpty()
    ) {
      return FALSE;
    }

    /** @var \Drupal\geolocation\Plugin\Field\FieldType\GeolocationItem $item */
    $item = $field->first();

    $values['lat'] = $item->get('lat')->getValue();
    $values['lng'] = $item->get('lng')->getValue();

    return $values;
  }

  /**
   * Processes the passed argument into an array of relevant geolocation data.
   *
   * @return array|bool
   *   The calculated values.
   */
  public function getParsedReferenceLocation() {
    // Cache the vales so this only gets processed once.
    static $values;

    if (!isset($values)) {
      if (empty($this->getValue())) {
        return [];
      }

      preg_match('/^([0-9]+)([<>=]+)([0-9.]+)(.*$)/', $this->getValue(), $values);

      if (
        empty($values)
        && is_numeric($this->getValue())
      ) {
        $values = $this->getCoordinatesFromEntityId($this->getValue());
        return $values;
      }

      $values = is_array($values) ? [
        'id' => (isset($values[1]) && is_numeric($values[1])) ? intval($values[1]) : FALSE,
        'operator' => (isset($values[2]) && in_array($values[2], [
          '<>',
          '=',
          '>=',
          '<=',
          '>',
          '<',
        ])) ? $values[2] : '<=',
        'distance' => (isset($values[3])) ? floatval($values[3]) : FALSE,
        'unit' => !empty($values[4]) ? $values[4] : 'km',
      ] : FALSE;

      $coordinates = $this->getCoordinatesFromEntityId($values['id']);
      if (empty($coordinates)) {
        return [];
      }

      $values = array_replace($values, $coordinates);
    }
    return $values;
  }

}
