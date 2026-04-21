<?php

namespace Drupal\geofield\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\geofield\Exception\HaversineUnavailableException;
use Drupal\geofield\Exception\InvalidPointException;
use Drupal\geofield\Exception\ProximityUnavailableException;
use Drupal\views\Plugin\views\HandlerBase;

/**
 * Base class for Geofield Proximity Source plugins.
 */
abstract class GeofieldProximitySourceBase extends PluginBase implements GeofieldProximitySourceInterface {

  use StringTranslationTrait;

  /**
   * The name of the constant defining the measurement unit.
   *
   * @var string
   */
  protected $units;

  /**
   * The view handler which uses this proximity plugin.
   *
   * @var \Drupal\views\Plugin\views\HandlerBase
   */
  protected $viewHandler;

  /**
   * The origin point to measure proximity from.
   *
   * @var array
   */
  protected $origin;

  /**
   * {@inheritdoc}
   */
  public function isValidLocation($lat, $lon) {
    return is_numeric($lat) && is_numeric($lon);
  }

  /**
   * {@inheritdoc}
   */
  public function isEmptyLocation($lat, $lon) {
    return (empty($lat) && empty($lon));
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(array &$form, FormStateInterface $form_state, array $options_parents, $is_exposed = FALSE) {
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(array &$form, FormStateInterface $form_state, array $options_parents) {
  }

  /**
   * {@inheritdoc}
   */
  public function getOrigin() {
    return $this->origin;
  }

  /**
   * {@inheritdoc}
   */
  public function setOrigin(array $origin) {
    return $this->origin = $origin;
  }

  /**
   * {@inheritdoc}
   */
  public function setUnits($units) {

    // If the given value is not a valid option, throw an error.
    if (!in_array($units, $this->getUnitsOptions())) {
      $message = $this->t('Invalid units supplied.');
      \Drupal::logger('geofield')->error($message);
      return FALSE;
    }

    // Otherwise set units to the given value.
    else {
      $this->units = $units;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getUnits() {
    return $this->units;
  }

  /**
   * Get the list of valid options for units.
   *
   * @return array
   *   The list of available unit types.
   */
  public function getUnitsOptions() {
    return array_keys(geofield_radius_options());
  }

  /**
   * {@inheritdoc}
   */
  public function setViewHandler(HandlerBase $view_handler) {
    $this->viewHandler = $view_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function getProximity($lat, $lon) {
    if (!$this->isValidLocation($lat, $lon)) {
      throw new InvalidPointException(sprintf('%s reports Invalid Point coordinates', get_class($this)));
    }

    // Fetch the value of the units that have been set for this class. The
    // constants are defined in the module file.
    $radius = constant($this->units);

    $origin = $this->getOrigin();
    if (!isset($origin['lat']) || !isset($origin['lon']) || $this->isEmptyLocation($origin['lat'], $origin['lon'])) {
      return NULL;
    }

    // Convert degrees to radians.
    $origin_latitude = deg2rad($origin['lat']);
    $origin_longitude = deg2rad($origin['lon']);
    $destination_latitude = deg2rad($lat);
    $destination_longitude = deg2rad($lon);

    // Calculate proximity.
    $proximity = $radius * acos(
        cos($origin_latitude)
        * cos($destination_latitude)
        * cos($destination_longitude - $origin_longitude)
        + sin($origin_latitude)
        * sin($destination_latitude)
      );

    if (!is_numeric($proximity)) {
      throw new ProximityUnavailableException(sprintf('%s not able to calculate valid Proximity value', get_class($this)));
    }

    // Check if proximity is NAN
    // (https://www.php.net/manual/en/function.is-nan.php)
    // NAN is returned from mathematical operations that are undefined,
    // This could happen for example in case of nearly identical locations.
    // @see: https://www.drupal.org/project/geofield/issues/3540091
    if (is_nan($proximity)) {
      return 0;
    }

    return $proximity;

  }

  /**
   * {@inheritdoc}
   */
  public function getHaversineOptions() {

    $origin = $this->getOrigin();
    if (!$origin || !isset($origin['lat']) || !isset($origin['lon'])) {
      throw new HaversineUnavailableException('Not able to calculate Haversine Options due to invalid Proximity Origin definition.');
    }
    if ($this->isEmptyLocation($origin['lat'], $origin['lon']) || !$this->isValidLocation($origin['lat'], $origin['lon'])) {
      return NULL;
    }
    return [
      'origin_latitude' => $origin['lat'],
      'origin_longitude' => $origin['lon'],
      'earth_radius' => constant($this->units),
    ];

  }

}
