<?php

declare(strict_types=1);

namespace Drupal\mukurtu_design\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\Radios;

/**
 * DesignPaletteRadios form element.
 *
 * @FormElement("mukurtu_palette_radios")
 */
class DesignPaletteRadios extends Radios {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = static::class;
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'processRadios'],
      ],
      '#theme_wrappers' => ['mukurtu_palette_radios'],
      '#pre_render' => [
        [$class, 'preRenderCompositeFormElement'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function processRadios(&$element, FormStateInterface $form_state, &$complete_form) {
    $element = parent::processRadios($element, $form_state, $complete_form);
    foreach (Element::children($element) as $radio) {
      $element[$radio]['#type'] = 'mukurtu_palette_radio';
    }
    return $element;
  }

}
