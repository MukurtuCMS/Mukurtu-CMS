<?php

declare(strict_types=1);

namespace Drupal\color_field;

/**
 * RGB represents the RGB color format.
 */
class ColorRGB extends ColorBase {

  /**
   * The red value (0-255).
   *
   * @var int
   */
  protected int $red;

  /**
   * The green value (0-255).
   *
   * @var int
   */
  protected int $green;

  /**
   * The blue value (0-255).
   *
   * @var int
   */
  protected int $blue;

  /**
   * Create a new RGB color.
   *
   * @param int $red
   *   The red (0-255)
   * @param int $green
   *   The green (0-255)
   * @param int $blue
   *   The blue (0-255)
   * @param float|null $opacity
   *   The opacity.
   */
  public function __construct(int $red, int $green, int $blue, ?float $opacity) {
    $this->red = max(0, min(255, $red));
    $this->green = max(0, min(255, $green));
    $this->blue = max(0, min(255, $blue));
    $this->opacity = $opacity;
  }

  /**
   * Get the red value (rounded).
   *
   * @return int
   *   The red value
   */
  public function getRed(): int {
    return $this->red;
  }

  /**
   * Get the green value (rounded).
   *
   * @return int
   *   The green value
   */
  public function getGreen(): int {
    return $this->green;
  }

  /**
   * Get the blue value (rounded).
   *
   * @return int
   *   The blue value
   */
  public function getBlue(): int {
    return $this->blue;
  }

  /**
   * A string representation of this color in the current format.
   *
   * @param bool $opacity
   *   Whether to display the opacity.
   *
   * @return string
   *   The color in format: #RRGGBB
   */
  public function toString(bool $opacity = TRUE): string {
    $output = $opacity
        ? 'rgba(' . $this->getRed() . ',' . $this->getGreen() . ',' . $this->getBlue() . ',' . $this->getOpacity() . ')'
        : 'rgb(' . $this->getRed() . ',' . $this->getGreen() . ',' . $this->getBlue() . ')';

    return strtolower($output);
  }

  /**
   * {@inheritdoc}
   */
  public function toHex(): ColorHex {
    return new ColorHex(
      $this->intToColorHex($this->getRed()) . $this->intToColorHex($this->getGreen()) . $this->intToColorHex($this->getBlue()),
      (float) $this->intToColorHex((int) $this->getOpacity() * 255)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function toRgb(): ColorRGB {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function toHsl(): ColorHSL {
    $r = $this->getRed() / 255;
    $g = $this->getGreen() / 255;
    $b = $this->getBlue() / 255;
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $l = ($max + $min) / 2;

    if ($max === $min) {
      // Achromatic.
      $h = $s = 0;
    }
    else {
      $d = $max - $min;
      $s = $l > 0.5
          ? $d / (2 - $max - $min)
          : $d / ($max + $min);

      switch ($max) {
        case $r:
          $h = ($g - $b) / $d + ($g < $b ? 6 : 0);
          break;

        case $g:
          $h = ($b - $r) / $d + 2;
          break;

        case $b:
          $h = ($r - $g) / $d + 4;
          break;
      }

      $h /= 6;
    }

    $h = floor($h * 360);
    $s = floor($s * 100);
    $l = floor($l * 100);

    return new ColorHSL(intval($h), intval($s), intval($l), $this->getOpacity());
  }

  /**
   * Get a padded color value in hex format (00 - FF) from an int (0-255)
   *
   * @param int $color
   *   The color value.
   *
   * @return string
   *   The color value in hex format.
   */
  protected function intToColorHex(int $color): string {
    return str_pad(dechex($color), 2, '0', STR_PAD_LEFT);
  }

}
