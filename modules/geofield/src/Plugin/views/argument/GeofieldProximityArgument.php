<?php

namespace Drupal\geofield\Plugin\views\argument;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\geofield\Plugin\GeofieldProximitySourceManager;
use Drupal\geofield\WktGenerator;
use Drupal\views\Plugin\views\argument\Formula;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Argument handler for geofield proximity.
 *
 * Argument format should be in the following format:
 * "40.73,-73.93<=5mi" (defaults to km).
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("geofield_proximity_argument")
 */
class GeofieldProximityArgument extends Formula implements ContainerFactoryPluginInterface {

  use LoggerChannelTrait;

  /**
   * The WktGenerator object.
   *
   * @var \Drupal\geofield\WktGenerator
   */
  protected $wktGenerator;

  /**
   * The geofield proximity manager.
   *
   * @var \Drupal\geofield\Plugin\GeofieldProximitySourceManager
   */
  protected $proximitySourceManager;

  /**
   * The Geofield Proximity Source Plugin.
   *
   * @var \Drupal\geofield\Plugin\GeofieldProximitySourceInterface
   */
  protected $sourcePlugin;

  /**
   * The Unites property.
   *
   * @var array
   */
  protected $units;

  /**
   * Get the decoded Unites.
   *
   * @return array
   *   The decoded units array.
   */
  protected function decodeUnits() {

    return [
      'km' => [
        'label' => $this->t('Kilometers'),
        'value' => 'GEOFIELD_KILOMETERS',
      ],
      'm' => [
        'label' => $this->t('Meters'),
        'value' => 'GEOFIELD_METERS',
      ],
      'mi' => [
        'label' => $this->t('Miles'),
        'value' => 'GEOFIELD_MILES',
      ],
      'yd' => [
        'label' => $this->t('Yards'),
        'value' => 'GEOFIELD_YARDS',
      ],
      'ft' => [
        'label' => $this->t('Feet'),
        'value' => 'GEOFIELD_FEET',
      ],
      'nmi' => [
        'label' => $this->T('Nautical Miles'),
        'value' => 'GEOFIELD_NAUTICAL_MILES',
      ],
    ];

  }

  /**
   * Get the markup list of the Unites.
   *
   * @return string
   *   The markup list of the Unites.
   */
  protected function unitsListMarkup() {
    $markup = '';
    foreach ($this->units as $k => $unit) {
      $markup .= '<br><strong>' . $k . '</strong> (for ' . $unit['label'] . ')';
    }
    return $markup;
  }

  /**
   * Constructs a Handler object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\geofield\WktGenerator $wkt_generator
   *   The WktGenerator object.
   * @param \Drupal\geofield\Plugin\GeofieldProximitySourceManager $proximity_source_manager
   *   The Geofield Proximity Source manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    WktGenerator $wkt_generator,
    GeofieldProximitySourceManager $proximity_source_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->wktGenerator = $wkt_generator;
    $this->proximitySourceManager = $proximity_source_manager;
    $this->units = $this->decodeUnits();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('geofield.wkt_generator'),
      $container->get('plugin.manager.geofield_proximity_source')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['description']['#markup'] .= $this->t('<br><u>Proximity format should be in the following format: <strong>"40.73,-73.93<=5[unit]"</strong></u>, where the operator might be also: ><br>and [unit] should be one of the following key value: @units_decodes.<br><u>Note:</u> Use dot (.) as decimal separator, and not comma (,), otherwise results won\'t be accurate.', [
      '@units_decodes' => Markup::create($this->unitsListMarkup()),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();
    $lat_alias = $this->realField . '_lat';
    $lon_alias = $this->realField . '_lon';

    try {
      /** @var \Drupal\geofield\Plugin\GeofieldProximitySourceInterface $source_plugin */
      $values = $this->getParsedReferenceLocation();
      if (!empty($values)) {
        $source_configuration = [
          'origin' => [
            'lat' => $values['lat'],
            'lon' => $values['lon'],
          ],
        ];
        $this->sourcePlugin = $this->proximitySourceManager->createInstance('geofield_context_filter', $source_configuration);
        $this->sourcePlugin->setViewHandler($this);
        $this->sourcePlugin->setUnits($values['units']);

        if ($haversine_options = $this->sourcePlugin->getHaversineOptions()) {
          $haversine_options['destination_latitude'] = $this->tableAlias . '.' . $lat_alias;
          $haversine_options['destination_longitude'] = $this->tableAlias . '.' . $lon_alias;
          $this->operator($haversine_options, $values['distance'], $values['operator']);
        }
      }
    }
    catch (\Exception $e) {
      $this->getLogger('geofield')->error($e->getMessage());
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function operator($options, $distance, $operator) {

    if (!empty($distance) && is_numeric($distance)) {
      /** @var \Drupal\views\Plugin\views\query\Sql $query */
      $query = $this->query;
      $query->addWhereExpression(0, geofield_haversine($options) . ' ' . $operator . ' ' . $distance);
    }
  }

  /**
   * Processes the passed argument into an array of relevant geolocation data.
   *
   * @return array|bool
   *   The calculated values.
   */
  public function getParsedReferenceLocation() {
    // Process argument values into an array.
    preg_match('/^([0-9\-.]+),+([0-9\-.]+)([<>=]+)([0-9.]+)(.*$)/', trim((string) $this->getValue()), $values);
    // Validate and return the passed argument.
    return is_array($values) && !empty($values) ? [
      'lat' => (isset($values[1]) && is_numeric($values[1]) && $values[1] >= -90 && $values[1] <= 90) ? floatval($values[1]) : FALSE,
      'lon' => (isset($values[2]) && is_numeric($values[2]) && $values[2] >= -180 && $values[2] <= 180) ? floatval($values[2]) : FALSE,
      'operator' => (isset($values[3]) && in_array($values[3], [
        '<>',
        '=',
        '>=',
        '<=',
        '>',
        '<',
      ])) ? $values[3] : '<=',
      'distance' => (isset($values[4])) ? floatval($values[4]) : FALSE,
      'units' => (isset($values[5]) && array_key_exists($values[5], $this->units)) ? $this->units[$values[5]]['value'] : 'GEOFIELD_KILOMETERS',
    ] : FALSE;
  }

}
