<?php

declare(strict_types=1);

namespace Drupal\color_field;

/**
 * ColorCMYK represents the CMYK color format.
 */
class ColorCMYK extends ColorCMY {

  /**
   * The key (black).
   *
   * @var int
   */
  protected int $key;

  /**
   * Create a new CMYK color.
   *
   * @param int $cyan
   *   The cyan.
   * @param int $magenta
   *   The magenta.
   * @param int $yellow
   *   The yellow.
   * @param int $key
   *   The key (black).
   * @param float|null $opacity
   *   The opacity.
   */
  public function __construct(int $cyan, int $magenta, int $yellow, int $key, ?float $opacity) {
    parent::__construct($cyan, $magenta, $yellow, $opacity);
    $this->key = $key;
  }

  /**
   * Get the key (black).
   *
   * @return int
   *   The amount of black.
   */
  public function getKey(): int {
    return $this->key;
  }

  /**
   * {@inheritdoc}
   */
  public function toRgb(): ColorRGB {
    return $this->toCmy()->toRgb();
  }

  /**
   * {@inheritdoc}
   */
  public function toCmy(): ColorCMY {
    $cyan = $this->cyan * (1 - $this->key) + $this->key;
    $magenta = $this->magenta * (1 - $this->key) + $this->key;
    $yellow = $this->yellow * (1 - $this->key) + $this->key;

    return new ColorCMY($cyan, $magenta, $yellow, $this->getOpacity());
  }

  /**
   * {@inheritdoc}
   */
  public function toHsl(): ColorHSL {
    return $this->toRgb()->toHsl();
  }

}
