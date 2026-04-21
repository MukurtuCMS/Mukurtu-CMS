<?php

declare(strict_types=1);

namespace Drupal\color_field;

/**
 * Hex represents the Hex color format.
 */
class ColorHex extends ColorBase {

  /**
   * The Hex triplet of the color as an int.
   *
   * @var int
   */
  protected int $color;

  /**
   * Create a new Hex from a string.
   *
   * @param string $color
   *   The string hex value (i.e. "FFFFFF").
   * @param float|null $opacity
   *   The opacity value.
   *
   * @throws \Exception
   *   If the color doesn't appear to be a valid hex value.
   */
  public function __construct(string $color, ?float $opacity) {
    $color = trim(strtolower($color));

    if (str_starts_with($color, '#')) {
      $color = substr($color, 1);
    }

    if (strlen($color) === 3) {
      $color = str_repeat($color[0], 2) . str_repeat($color[1], 2) . str_repeat($color[2], 2);
    }

    if (!preg_match('/[0-9A-F]{6}/i', $color)) {
      throw new \Exception("Color $color doesn't appear to be a valid hex value");
    }

    $this->color = hexdec($color);
    $opacity = $opacity ?? 1.0;
    $this->setOpacity((float) $opacity);
  }

  /**
   * A string representation of this color in the current format.
   *
   * @param bool $opacity
   *   Whether to display the opacity.
   *
   * @return string
   *   The color in format: #RRGGBB.
   */
  public function toString(bool $opacity = TRUE): string {
    $rgb = $this->toRgb();
    $hex = '#';
    $hex .= str_pad(dechex($rgb->getRed()), 2, "0", STR_PAD_LEFT);
    $hex .= str_pad(dechex($rgb->getGreen()), 2, "0", STR_PAD_LEFT);
    $hex .= str_pad(dechex($rgb->getBlue()), 2, "0", STR_PAD_LEFT);

    if ($opacity) {
      $hex .= ' ' . $this->getOpacity();
    }

    return strtolower($hex);
  }

  /**
   * {@inheritdoc}
   */
  public function toHex(): ColorHex {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function toRgb(): ColorRGB {
    $red = (($this->color & 0xFF0000) >> 16);
    $green = (($this->color & 0x00FF00) >> 8);
    $blue = (($this->color & 0x0000FF));
    $opacity = $this->getOpacity();

    return new ColorRGB($red, $green, $blue, $opacity);
  }

  /**
   * {@inheritdoc}
   */
  public function toHsl(): ColorHsl {
    return $this->toRGB()->toHsl();
  }

}
