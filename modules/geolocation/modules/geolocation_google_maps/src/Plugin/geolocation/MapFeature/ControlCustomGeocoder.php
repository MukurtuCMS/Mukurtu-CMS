<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\GeocoderManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Geocoding control element.
 *
 * @MapFeature(
 *   id = "control_geocoder",
 *   name = @Translation("Map Control - Geocoder"),
 *   description = @Translation("Add address search with geocoding functionality map."),
 *   type = "google_maps",
 * )
 */
class ControlCustomGeocoder extends ControlCustomElementBase {

  /**
   * The GeocoderManager object.
   *
   * @var \Drupal\geolocation\GeocoderManager
   */
  protected $geocoderManager;

  /**
   * ControlCustomGeocoder constructor.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin configuration.
   * @param \Drupal\geolocation\GeocoderManager $geocoder_manager
   *   Geocoder manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, GeocoderManager $geocoder_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->geocoderManager = $geocoder_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geolocation.geocoder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return array_replace_recursive(
      parent::getDefaultSettings(),
      [
        'geocoder' => 'google_geocoding_api',
        'settings' => [],
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents) {
    $form = parent::getSettingsForm($settings, $parents);

    $settings = array_replace_recursive(
      self::getDefaultSettings(),
      $settings
    );

    $geocoder_options = [];
    foreach ($this->geocoderManager->getDefinitions() as $id => $definition) {
      if (empty($definition['frontendCapable'])) {
        continue;
      }
      $geocoder_options[$id] = $definition['name'];
    }

    if ($geocoder_options) {
      $form['geocoder'] = [
        '#type' => 'select',
        '#options' => $geocoder_options,
        '#title' => $this->t('Geocoder plugin'),
        '#default_value' => $settings['geocoder'],
        '#ajax' => [
          'callback' => [
            get_class($this->geocoderManager), 'addGeocoderSettingsFormAjax',
          ],
          'wrapper' => 'google-control-geocoder-plugin-settings',
          'effect' => 'fade',
        ],
      ];

      if (!empty($settings['geocoder'])) {
        $geocoder_plugin = $this->geocoderManager->getGeocoder(
            $settings['geocoder'],
            $settings['settings']
          );
      }
      elseif (current(array_keys($geocoder_options))) {
        $geocoder_plugin = $this->geocoderManager->getGeocoder(current(array_keys($geocoder_options)));
      }

      if (!empty($geocoder_plugin)) {
        $geocoder_settings_form = $geocoder_plugin->getOptionsForm();
        if ($geocoder_settings_form) {
          $form['settings'] = $geocoder_settings_form;
        }
      }

      if (empty($form['settings'])) {
        $form['settings'] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->t("No settings available."),
        ];
      }

      $form['settings'] = array_replace_recursive($form['settings'], [
        '#flatten' => TRUE,
        '#prefix' => '<div id="google-control-geocoder-plugin-settings">',
        '#suffix' => '</div>',
      ]);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $render_array, array $feature_settings, array $context = []) {
    $render_array = parent::alterMap($render_array, $feature_settings, $context);

    /** @var \Drupal\geolocation\GeocoderInterface $geocoder_plugin */
    $geocoder_plugin = $this->geocoderManager->getGeocoder(
      $feature_settings['geocoder'],
      $feature_settings['settings']
    );

    if (empty($geocoder_plugin)) {
      return $render_array;
    }

    $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
      empty($render_array['#attached']) ? [] : $render_array['#attached'],
      [
        'library' => [
          'geolocation_google_maps/mapfeature.' . $this->getPluginId(),
        ],
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $render_array['#id'] => [
                $this->getPluginId() => [
                  'enable' => TRUE,
                ],
              ],
            ],
          ],
        ],
      ]
    );

    $geocoder_plugin->formAttachGeocoder($render_array['#controls'][$this->pluginId], $render_array['#id']);

    return $render_array;
  }

}
