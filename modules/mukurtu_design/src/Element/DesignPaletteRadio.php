<?php

declare(strict_types=1);

namespace Drupal\mukurtu_design\Element;

use Drupal\Core\Render\Element\Radio;

/**
 * DesignPaletteRadio form element.
 *
 * @FormElement("mukurtu_palette_radio")
 */
class DesignPaletteRadio extends Radio {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = static::class;
    return [
      '#input' => TRUE,
      '#default_value' => NULL,
      '#process' => [
        [$class, 'processAjaxForm'],
      ],
      '#pre_render' => [
        [$class, 'preRenderRadio'],
      ],
      '#theme' => 'input__radio',
      '#theme_wrappers' => ['mukurtu_palette_radio'],
      '#title_display' => 'after',
    ];
  }

}
