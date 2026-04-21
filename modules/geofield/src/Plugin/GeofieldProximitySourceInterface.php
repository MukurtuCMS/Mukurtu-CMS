<?php

namespace Drupal\geofield\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\HandlerBase;

/**
 * Defines an interface for Geofield Proximity Source plugins.
 */
interface GeofieldProximitySourceInterface extends PluginInspectionInterface {

  /**
   * Check for a valid couple of latitude and longitude.
   *
   * @param float $lat
   *   The latitude value.
   * @param float $lon
   *   The longitude value.
   *
   * @return bool
   *   The flag indicates whether location is valid.
   *
   * @todo Add more tests, particularly around max/min values.
   */
  public function isValidLocation($lat, $lon);

  /**
   * Check if Location is empty.
   *
   * @param float $lat
   *   The latitude value.
   * @param float $lon
   *   The longitude value.
   *
   * @return bool
   *   The bool result.
   */
  public function isEmptyLocation($lat, $lon);

  /**
   * Builds the specific form elements for the geofield proximity plugin.
   *
   * @param array $form
   *   The form element to build.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $options_parents
   *   The values parents.
   * @param bool $is_exposed
   *   The check/differentiate if it is part of an exposed form.
   */
  public function buildOptionsForm(array &$form, FormStateInterface $form_state, array $options_parents, $is_exposed = FALSE);

  /**
   * Validates the options form for the geofield proximity plugin.
   *
   * @param array $form
   *   The form element to build.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $options_parents
   *   The values parents.
   */
  public function validateOptionsForm(array &$form, FormStateInterface $form_state, array $options_parents);

  /**
   * Set the units to perform the calculation in.
   *
   * @param string $units
   *   The name of the units constant to be used or string representation of it.
   */
  public function setUnits($units);

  /**
   * Get the current units.
   *
   * @return string
   *   The name of the units constant to be used or string representation of it.
   */
  public function getUnits();

  /**
   * Sets view handler which uses this proximity plugin.
   *
   * @param \Drupal\views\Plugin\views\HandlerBase $view_handler
   *   The view handler which uses this proximity plugin.
   */
  public function setViewHandler(HandlerBase $view_handler);

  /**
   * Get the calculated proximity.
   *
   * @param float $lat
   *   The current point latitude.
   * @param float $lon
   *   The current point longitude.
   *
   * @return float
   *   The calculated proximity.
   *
   * @throws \Drupal\geofield\Exception\InvalidPointException;
   *   If the proximity cannot be created, due to incorrect point coordinates
   *   definition.
   *
   * @throws \Drupal\geofield\Exception\ProximityUnavailableException;
   *   If any other case the proximity value cannot be created correctly.
   */
  public function getProximity($lat, $lon);

  /**
   * Gets the haversine options.
   *
   * @return array
   *   The haversine options.
   *
   * @throws \Drupal\geofield\Exception\HaversineUnavailableException;
   *   If the haversine is unavailable, due to incorrect setup definitions.
   */
  public function getHaversineOptions();

  /**
   * Gets the proximity distance origin.
   *
   * @return array
   *   The proximity distance origin.
   */
  public function getOrigin();

  /**
   * Sets the proximity distance origin.
   *
   * @param array $origin
   *   The proximity distance origin.
   */
  public function setOrigin(array $origin);

}
