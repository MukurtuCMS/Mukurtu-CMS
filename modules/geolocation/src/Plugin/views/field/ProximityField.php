<?php

namespace Drupal\geolocation\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\geolocation\LocationManager;
use Drupal\geolocation\ProximityTrait;
use Drupal\views\Plugin\views\field\NumericField;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler for geolocation field.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("geolocation_field_proximity")
 */
class ProximityField extends NumericField implements ContainerFactoryPluginInterface {

  use ProximityTrait;

  /**
   * Location manager.
   *
   * @var \Drupal\geolocation\LocationManager
   */
  protected $locationManager;

  /**
   * Constructs a Handler object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\geolocation\LocationManager $location_manager
   *   Location manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LocationManager $location_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->locationManager = $location_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geolocation.location')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['center'] = ['default' => []];
    $options['display_unit'] = ['default' => 'km'];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['center'] = $this->locationManager->getLocationOptionsForm($this->options['center'], $this);

    $form['display_unit'] = [
      '#title' => $this->t('Distance unit'),
      '#description' => $this->t('Values internally are always treated as kilometers. This setting converts values accordingly.'),
      '#type' => 'select',
      '#weight' => 5,
      '#default_value' => $this->options['display_unit'],
      '#options' => [
        'km' => $this->t('Kilometer'),
        'mi' => $this->t('Miles'),
        'nm' => $this->t('Nautical Miles'),
        'm' => $this->t('Meter'),
        'ly' => $this->t('Light-years'),
      ],
    ];
  }

  /**
   * Get center value.
   *
   * @return array
   *   Center value.
   */
  protected function getCenter() {
    return $this->locationManager->getLocation($this->options['center'], $this);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;

    $center = $this->getCenter();
    if (empty($center)) {
      return;
    }

    // Build the query expression.
    $expression = self::getProximityQueryFragment($this->ensureMyTable(), $this->realField, $center['lat'], $center['lng']);

    // Get a placeholder for this query and save the field_alias for it.
    // Remove the initial ':' from the placeholder and avoid collision with
    // original field name.
    $this->field_alias = $query->addField(NULL, $expression, substr($this->placeholder(), 1));
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $values, $field = NULL) {
    $value = parent::getValue($values, $field);
    $value = self::convertDistance((float) $value, $this->options['display_unit'], TRUE);

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $row) {

    // Remove once https://www.drupal.org/node/1232920 lands.
    $value = $this->getValue($row);
    // Hiding should happen before rounding or adding prefix/suffix.
    if ($this->options['hide_empty'] && empty($value) && ($value !== 0 || $this->options['empty_zero'])) {
      return '';
    }
    return parent::render($row);
  }

}
