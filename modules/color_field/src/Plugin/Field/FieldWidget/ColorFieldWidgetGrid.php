<?php

declare(strict_types=1);

namespace Drupal\color_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'color_field_default' widget.
 *
 * @FieldWidget(
 *   id = "color_field_widget_grid",
 *   module = "color_field",
 *   label = @Translation("Color grid"),
 *   field_types = {
 *     "color_field_type"
 *   }
 * )
 */
class ColorFieldWidgetGrid extends ColorFieldWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = [];

    $element['cell_width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cell width'),
      '#default_value' => $this->getSetting('cell_width'),
      '#required' => TRUE,
      '#description' => $this->t('Width of each individual color cell.'),
    ];
    $element['cell_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Height width'),
      '#default_value' => $this->getSetting('cell_height'),
      '#required' => TRUE,
      '#description' => $this->t('Height of each individual color cell.'),
    ];
    $element['cell_margin'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cell margin'),
      '#default_value' => $this->getSetting('cell_margin'),
      '#required' => TRUE,
      '#description' => $this->t('Margin of each individual color cell.'),
    ];
    $element['box_width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Box width'),
      '#default_value' => $this->getSetting('box_width'),
      '#required' => TRUE,
      '#description' => $this->t('Width of the color display box.'),
    ];
    $element['box_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Box height'),
      '#default_value' => $this->getSetting('box_height'),
      '#required' => TRUE,
      '#description' => $this->t('Height of the color display box.'),
    ];
    $element['columns'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Columns number'),
      '#default_value' => $this->getSetting('columns'),
      '#required' => TRUE,
      '#description' => $this->t('Number of columns to display. Color order may look strange if this is altered.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];

    $cell_width = $this->getSetting('cell_width');
    $cell_height = $this->getSetting('cell_height');
    $cell_margin = $this->getSetting('cell_margin');
    $box_width = $this->getSetting('box_width');
    $box_height = $this->getSetting('box_height');
    $columns = $this->getSetting('columns');

    if (!empty($cell_width)) {
      $summary[] = $this->t('Cell width: @cell_width', ['@cell_width' => $cell_width]);
    }

    if (!empty($cell_height)) {
      $summary[] = $this->t('Cell height: @cell_height', ['@cell_height' => $cell_height]);
    }

    if (!empty($cell_margin)) {
      $summary[] = $this->t('Cell margin: @cell_margin', ['@cell_margin' => $cell_margin]);
    }

    if (!empty($box_width)) {
      $summary[] = $this->t('Box width: @box_width', ['@box_width' => $box_width]);
    }

    if (!empty($box_height)) {
      $summary[] = $this->t('Box height: @box_height', ['@box_height' => $box_height]);
    }

    if (!empty($columns)) {
      $summary[] = $this->t('Columns: @columns', ['@columns' => $columns]);
    }

    if (empty($summary)) {
      $summary[] = $this->t('No placeholder');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // We are nesting some sub-elements inside the parent, so we need a wrapper.
    // We also need to add another #title attribute at the top level for ease in
    // identifying this item in error messages. We do not want to display this
    // title because the actual title display is handled at a higher level by
    // the Field module.
    // $element['#theme_wrappers'] = array('color_field_widget_grid');.
    $element['#attached']['library'][] = 'color_field/color-field-widget-grid';

    // Set Drupal settings.
    $settings = $this->getSettings();
    $element['#attached']['drupalSettings']['color_field']['color_field_widget_grid'][$element['#uid']] = $settings;

    $element['color']['#attributes']['class'][] = 'js-color-field-widget-grid__color';
    $element['color']['#attributes']['id'] = $element['#uid'];
    $element['color']['#wrapper_attributes']['class'][] = 'clearfix';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'cell_width' => 10,
      'cell_height' => 10,
      'cell_margin' => 1,
      'box_width' => 115,
      'box_height' => 20,
      'columns' => 16,
    ] + parent::defaultSettings();
  }

}
