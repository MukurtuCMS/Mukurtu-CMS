<?php

namespace Drupal\geofield\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield\DmsConverter;

/**
 * Plugin implementation of the 'geofield_latlon' formatter.
 *
 * @FieldFormatter(
 *   id = "geofield_latlon",
 *   label = @Translation("Lat/Lon"),
 *   field_types = {
 *     "geofield"
 *   }
 * )
 */
class LatLonFormatter extends GeofieldDefaultFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'output_format' => 'decimal',
    ];
  }

  /**
   * Helper function to get the formatter settings options.
   *
   * @return array
   *   The formatter settings options.
   */
  protected function formatOptions() {
    return [
      'decimal' => $this->t("Decimal Format (17.76972)"),
      'dms' => $this->t("DMS Format (17° 46' 11'' N)"),
      'dm' => $this->t("DM Format (17° 46.19214' N)"),
      'wkt' => $this->t("WKT"),
    ];
  }

  /**
   * Returns the output format, set or default one.
   *
   * @return string
   *   The output format string.
   */
  protected function getOutputFormat() {
    return in_array($this->getSetting('output_format'), array_keys($this->formatOptions())) ? $this->getSetting('output_format') : self::defaultSettings()['output_format'];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    unset($elements['output_escape']);

    $elements['output_format'] = [
      '#title' => $this->t('Output Format'),
      '#type' => 'select',
      '#default_value' => $this->getOutputFormat(),
      '#options' => $this->formatOptions(),
      '#required' => TRUE,
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary[] = $this->t('Geospatial output format: @format', ['@format' => $this->formatOptions()[$this->getOutputFormat()]]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $output = ['#markup' => ''];
      $geom = $this->geoPhpWrapper->load($item->value);
      if ($geom) {
        // If the geometry is not a point, get the centroid.
        if ($geom->getGeomType() != 'Point') {
          $geom = $geom->centroid();
        }
        /** @var \Point $geom */
        if ($this->getOutputFormat() == 'decimal') {
          $output = [
            '#theme' => 'geofield_latlon',
            '#lat' => $geom->y(),
            '#lon' => $geom->x(),
          ];
        }
        elseif ($this->getOutputFormat() == 'wkt') {
          $output = [
            '#markup' => "POINT ({$geom->x()} {$geom->y()})",
          ];
        }
        else {
          $components = $this->getDmsComponents($geom);
          $output = [
            '#theme' => 'geofield_dms',
            '#components' => $components,
          ];
        }
      }
      $elements[$delta] = $output;
    }

    return $elements;
  }

  /**
   * Generates the DMS expected components given a Point.
   *
   * @param \Point $point
   *   The point to represent as DMS.
   *
   * @return array
   *   The DMS LatLon components
   */
  protected function getDmsComponents(\Point $point) {
    $dms_point = DmsConverter::decimalToDms($point->x(), $point->y());
    $components = [];
    foreach (['lat', 'lon'] as $component) {
      $item = $dms_point->get($component);
      if ($this->getSetting('output_format') == 'dm') {
        $item['minutes'] = number_format($item['minutes'] + ($item['seconds'] / 60), 5);
        $item['seconds'] = NULL;
      }
      $components[$component] = $item;
    }
    return $components;
  }

}
