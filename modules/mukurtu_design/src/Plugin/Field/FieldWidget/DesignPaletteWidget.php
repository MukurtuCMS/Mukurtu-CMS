<?php

declare(strict_types=1);

namespace Drupal\mukurtu_design\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsButtonsWidget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'mukurtu_palette_select' widget.
 *
 * @FieldWidget(
 *   id = "mukurtu_palette_select",
 *   label = @Translation("Mukurtu Palette select"),
 *   field_types = {
 *     "list_string"
 *   },
 *   multiple_values = TRUE
 * )
 */
class DesignPaletteWidget extends OptionsButtonsWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['#type'] = 'mukurtu_palette_radios';
    $element['#attached']['library'][] = 'mukurtu_v4/palettes_demo';

    return $element;
  }

}
