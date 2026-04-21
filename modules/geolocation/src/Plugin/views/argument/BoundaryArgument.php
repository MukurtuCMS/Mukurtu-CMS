<?php

namespace Drupal\geolocation\Plugin\views\argument;

use Drupal\Core\Form\FormStateInterface;
use Drupal\geolocation\BoundaryTrait;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;
use Drupal\views\Plugin\views\query\Sql;

/**
 * Argument handler for geolocation boundary.
 *
 * Argument format should be in the following format:
 * NE-Lat,NE-Lng,SW-Lat,SW-Lng, so "11.1,33.3,55.5,77.7".
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("geolocation_argument_boundary")
 */
class BoundaryArgument extends ArgumentPluginBase {

  use BoundaryTrait;

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['description']['#markup'] .= $this->t('<br/>Boundary format should be in a NE-Lat,NE-Lng,SW-Lat,SW-Lng format: <strong>"11.1,33.3,55.5,77.7"</strong> .');
  }

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $values = $this->getParsedBoundary();
    if (!($this->query instanceof Sql)) {
      return;
    }

    if (empty($values)) {
      return;
    }

    // Get the field alias.
    $lat_north_east = $values['lat_north_east'];
    $lng_north_east = $values['lng_north_east'];
    $lat_south_west = $values['lat_south_west'];
    $lng_south_west = $values['lng_south_west'];

    if (
      !is_numeric($lat_north_east)
      || !is_numeric($lng_north_east)
      || !is_numeric($lat_south_west)
      || !is_numeric($lng_south_west)
    ) {
      return;
    }

    $this->query->addWhereExpression(
      $group_by,
      self::getBoundaryQueryFragment($this->ensureMyTable(), $this->realField, $lat_north_east, $lng_north_east, $lat_south_west, $lng_south_west)
    );
  }

  /**
   * Processes the passed argument into an array of relevant geolocation data.
   *
   * @return array|bool
   *   The calculated values.
   */
  public function getParsedBoundary() {
    // Cache the vales so this only gets processed once.
    static $values;

    if (!isset($values)) {
      // Process argument values into an array.
      preg_match('/^([0-9\-.]+),+([0-9\-.]+),+([0-9\-.]+),+([0-9\-.]+)(.*$)/', $this->getValue(), $values);
      // Validate and return the passed argument.
      $values = is_array($values) ? [
        'lat_north_east' => (isset($values[1]) && is_numeric($values[1]) && $values[1] >= -90 && $values[1] <= 90) ? floatval($values[1]) : FALSE,
        'lng_north_east' => (isset($values[2]) && is_numeric($values[2]) && $values[2] >= -180 && $values[2] <= 180) ? floatval($values[2]) : FALSE,
        'lat_south_west' => (isset($values[2]) && is_numeric($values[3]) && $values[3] >= -90 && $values[3] <= 90) ? floatval($values[3]) : FALSE,
        'lng_south_west' => (isset($values[2]) && is_numeric($values[4]) && $values[4] >= -180 && $values[4] <= 180) ? floatval($values[4]) : FALSE,
      ] : FALSE;
    }
    return $values;
  }

}
