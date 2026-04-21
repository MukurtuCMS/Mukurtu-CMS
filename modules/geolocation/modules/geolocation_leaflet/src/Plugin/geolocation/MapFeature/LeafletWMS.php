<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapFeatureBase;

/**
 * Provides Web Map services.
 *
 * @MapFeature(
 *   id = "leaflet_wms",
 *   name = @Translation("Web Map services"),
 *   description = @Translation("Provide single-tile/untiled/nontiled layers, shared WMS sources, and GetFeatureInfo-powered identify."),
 *   type = "leaflet",
 * )
 */
class LeafletWMS extends MapFeatureBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'url' => '',
      'version' => '1.1.1',
      'layers' => '',
      'styles' => '',
      'srs' => '',
      'format' => 'image/jpeg',
      'transparent' => FALSE,
      'identify' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents) {
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service url'),
      '#default_value' => $settings['url'],
    ];
    $form['version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service version'),
      '#default_value' => $settings['version'],
    ];
    $form['layers'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Layers to display on map'),
      '#description' => $this->t('Value is a comma-separated list of layer names.'),
      '#default_value' => $settings['layers'],
    ];
    $form['styles'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Styles in which layers are to be rendered'),
      '#description' => $this->t('Value is a comma-separated list of style names, or empty if default styling is required. Style names may be empty in the list, to use default layer styling.'),
      '#default_value' => $settings['styles'],
    ];
    $form['srs'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Spatial Reference System'),
      '#description' => $this->t('Value is in form %srs.', ['%srs' => 'EPSG:nnn']),
      '#default_value' => $settings['srs'],
    ];
    $form['format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Format for the map output'),
      '#description' => $this->t(
        'See <a href="@url">WMS output formats</a> for supported values.',
        ['@url' => 'https://docs.geoserver.org/stable/en/user/services/wms/outputformats.html#wms-output-formats']
      ),
      '#default_value' => $settings['format'],
    ];
    $form['transparent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Transparent'),
      '#description' => $this->t('Whether the map background should be transparent.'),
      '#default_value' => $settings['transparent'],
    ];
    $form['identify'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Identify'),
      '#description' => $this->t('Call the WMS GetFeatureInfo service to query a map layer and return information about the underlying features.'),
      '#default_value' => $settings['identify'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $render_array, array $feature_settings, array $context = []) {
    $render_array = parent::alterMap($render_array, $feature_settings, $context);

    $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
      empty($render_array['#attached']) ? [] : $render_array['#attached'],
      [
        'library' => [
          'geolocation_leaflet/mapfeature.' . $this->getPluginId(),
        ],
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $render_array['#id'] => [
                $this->getPluginId() => [
                  'enable' => TRUE,
                  'url' => $feature_settings['url'],
                  'version' => $feature_settings['version'],
                  'layers' => $feature_settings['layers'],
                  'styles' => $feature_settings['styles'],
                  'srs' => $feature_settings['srs'],
                  'format' => $feature_settings['format'],
                  'transparent' => $feature_settings['transparent'],
                  'identify' => $feature_settings['identify'],
                ],
              ],
            ],
          ],
        ],
      ]
    );

    return $render_array;
  }

}
