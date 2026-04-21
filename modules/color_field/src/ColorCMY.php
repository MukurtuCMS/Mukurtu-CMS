<?php

declare(strict_types=1);

namespace Drupal\color_field;

/**
 * ColorCMY represents the CMY color format.
 */
class ColorCMY extends ColorBase {

  /**
   * The cyan.
   *
   * @var int
   */
  protected int $cyan;

  /**
   * The magenta.
   *
   * @var int
   */
  protected int $magenta;

  /**
   * The yellow.
   *
   * @var int
   */
  protected int $yellow;

  /**
   * Create a new CMYK color.
   *
   * @param int $cyan
   *   The cyan.
   * @param int $magenta
   *   The magenta.
   * @param int $yellow
   *   The yellow.
   * @param float|null $opacity
   *   The opacity.
   */
  public function __construct(int $cyan, int $magenta, int $yellow, ?float $opacity) {
    $this->cyan = $cyan;
    $this->magenta = $magenta;
    $this->yellow = $yellow;
    $this->opacity = $opacity;
  }

  /**
   * Get the amount of Cyan.
   *
   * @return int
   *   The amount of cyan.
   */
  public function getCyan(): int {
    return $this->cyan;
  }

  /**
   * Get the amount of Magenta.
   *
   * @return int
   *   The amount of magenta.
   */
  public function getMagenta(): int {
    return $this->magenta;
  }

  /**
   * Get the amount of Yellow.
   *
   * @return int
   *   The amount of yellow.
   */
  public function getYellow(): int {
    return $this->yellow;
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
    return $this->toHex()->toString($opacity);
  }

  /**
   * {@inheritdoc}
   */
  public function toHex(): ColorHex {
    return $this->toRgb()->toHex();
  }

  /**
   * {@inheritdoc}
   */
  public function toRgb(): ColorRGB {
    $red = (1 - $this->cyan) * 255;
    $green = (1 - $this->magenta) * 255;
    $blue = (1 - $this->yellow) * 255;

    return new ColorRGB($red, $green, $blue, $this->getOpacity());
  }

  /**
   * {@inheritdoc}
   */
  public function toCmyk(): ColorCMYK {
    $key = min($this->getCyan(), $this->getMagenta(), $this->getYellow());
    $cyan = $this->cyan * (1 - $key) + $key;
    $magenta = $this->magenta * (1 - $key) + $key;
    $yellow = $this->yellow * (1 - $key) + $key;

    return new ColorCMYK($cyan, $magenta, $yellow, $key, $this->getOpacity());
  }

  /**
   * {@inheritdoc}
   */
  public function toHsl(): ColorHSL {
    return $this->toRgb()->toHsl();
  }

}
