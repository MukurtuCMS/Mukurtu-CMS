<?php

namespace Drupal\geolocation\Plugin\geolocation\LocationInput;

use Drupal\geolocation\LocationInputBase;
use Drupal\geolocation\LocationInputInterface;

/**
 * Location based proximity center.
 *
 * @LocationInput(
 *   id = "coordinates",
 *   name = @Translation("Coordinates input"),
 *   description = @Translation("Simple latitude, longitude input."),
 * )
 */
class Coordinates extends LocationInputBase implements LocationInputInterface {}
