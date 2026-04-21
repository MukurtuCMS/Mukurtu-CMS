<?php

namespace Drupal\geolocation\Plugin\views\argument;

use Drupal\Core\Form\FormStateInterface;
use Drupal\geolocation\ProximityTrait;
use Drupal\views\Plugin\views\argument\Formula;
use Drupal\views\Plugin\views\query\Sql;

/**
 * Argument handler for geolocation proximity.
 *
 * Argument format should be in the following format:
 * "37.7749295,-122.41941550000001<=5mi" (defaults to km).
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("geolocation_argument_proximity")
 */
class ProximityArgument extends Formula {

  use ProximityTrait;

  /**
   * The condition operator.
   *
   * @var string
   */
  public string $operator = '<';

  /**
   * Distance.
   *
   * @var int
   */
  protected $distance = 0;

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['description']['#markup'] .= $this->t('<br/> Proximity format should be in the following format: <strong>"37.7749295,-122.41941550000001<=5mi"</strong> (defaults to km).');
  }

  /**
   * Get the formula for this argument.
   */
  public function getFormula() {
    // Parse argument for reference location.
    $values = $this->getParsedReferenceLocation();
    // Make sure we have enough information to start with.
    if (
      !empty($values)
      && isset($values['lat'])
      && isset($values['lng'])
      && isset($values['distance'])
    ) {
      $distance = self::convertDistance((float) $values['distance'], $values['unit']);

      // Build a formula for the where clause.
      $formula = self::getProximityQueryFragment($this->tableAlias, $this->realField, $values['lat'], $values['lng']);
      // Set the operator and value for the query.
      $this->operator = $values['operator'];
      $this->distance = $distance;

      return !empty($formula) ? str_replace('***table***', $this->tableAlias, $formula) : FALSE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();
    // Now that our table is secure, get our formula.
    $placeholder = $this->placeholder();
    $formula = $this->getFormula();
    if (!$formula) {
      return;
    }
    $formula .= ' ' . $this->operator . ' ' . $placeholder;
    $placeholders = [
      $placeholder => $this->distance,
    ];

    // The addWhere function is only available for SQL queries.
    if ($this->query instanceof Sql) {
      $this->query->addWhere(0, $formula, $placeholders, 'formula');
    }
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
      // Get the value.
      $value = $this->getValue();

      // Set static values to empty array and return early if no argument.
      if (empty($value)) {
        $values = [];
        return FALSE;
      }
      // Process argument values into an array.
      preg_match('/^([0-9\-.]+),+([0-9\-.]+)([<>=]+)([0-9.]+)(.*$)/', $this->getValue(), $values);

      // Validate latitude is set and in range.
      if (!(isset($values[1]) && $values[1] >= -90 && $values[1] <= 90)) {
        return FALSE;
      }

      // Validate longitude is set and in range.
      if (!(isset($values[2]) && $values[2] >= -180 && $values[2] <= 180)) {
        return FALSE;
      }

      // Validate operator is in a list of permitted values.
      if (!(isset($values[3])
        && in_array($values[3], ['<>', '=', '>=', '<=', '>', '<']))) {
        return FALSE;
      }

      // Validate distance is positive and non-zero.
      if (!(isset($values[4]) && $values[4] > 0)) {
        return FALSE;
      }

      $values = is_array($values) ? [
        'lat' => floatval($values[1]),
        'lng' => floatval($values[2]),
        'operator' => $values[3],
        'distance' => floatval($values[4]),
        'unit' => $values[5] ?? 'km',
      ] : FALSE;
    }
    return $values;
  }

}
