<?php

declare(strict_types=1);

namespace Drupal\color_field\Plugin\migrate\field;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_drupal\Plugin\migrate\field\FieldPluginBase;

/**
 * Field Plugin for color field migrations.
 *
 * @MigrateField(
 *   id = "color_field",
 *   core = {7},
 *   type_map = {
 *     "color_field_rgb" = "color_field_type",
 *   },
 *   source_module = "color_field",
 *   destination_module = "color_field",
 * )
 */
class ColorField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defineValueProcessPipeline(MigrationInterface $migration, $field_name, $data) {
    $process = [
      'plugin' => 'sub_process',
      'source' => $field_name,
      'process' => [
        'color' => 'rgb',
        'opacity' => 'opacity',
      ],
    ];

    $migration->setProcessOfProperty($field_name, $process);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldWidgetMap(): array {
    return [
      'color_field_default_widget' => 'color_field_widget_box',
      'color_field_simple_widget' => 'color_field_widget_grid',
      'color_field_spectrum_widget' => 'color_field_widget_html5',
      'color_field_plain_text' => 'color_field_widget_default',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldFormatterMap(): array {
    return [
      'color_field_default_formatter' => 'color_field_formatter_text',
      'color_field_css_declaration' => 'color_field_formatter_css',
      'color_field_swatch' => 'color_field_formatter_swatch',
    ];
  }

}
