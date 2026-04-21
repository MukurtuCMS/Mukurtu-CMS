<?php

namespace Drupal\field_group_accordion\Element;

use Drupal\Core\Render\Element\RenderElementBase;

/**
 * Provides a render element for an accordion item.
 *
 * @FormElement("field_group_accordion_item")
 */
class AccordionItem extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);

    return [
      '#process' => [
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderGroup'],
      ],
      '#open' => FALSE,
      '#theme_wrappers' => ['field_group_accordion_item'],
    ];
  }

}
