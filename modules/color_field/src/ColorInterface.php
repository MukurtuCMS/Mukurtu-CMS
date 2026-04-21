<?php

declare(strict_types=1);

namespace Drupal\color_field;

/**
 * Defines a common interface for color classes.
 */
interface ColorInterface {

  /**
   * Get the color as a string.
   *
   * @return string
   *   The color as a string.
   */
  public function toString(): string;

  /**
   * Get the color as a hex instance.
   *
   * @return \Drupal\color_field\ColorHex
   *   The color as a hex instance.
   */
  public function toHex(): ColorHex;

  /**
   * Get the color as an RGB instance.
   *
   * @return \Drupal\color_field\ColorRGB
   *   The color as an RGB instance.
   */
  public function toRgb(): ColorRGB;

  /**
   * Get the color as an HSL instance.
   *
   * @return \Drupal\color_field\ColorHSL
   *   The color as an HSL instance.
   */
  public function toHsl(): ColorHSL;

}
