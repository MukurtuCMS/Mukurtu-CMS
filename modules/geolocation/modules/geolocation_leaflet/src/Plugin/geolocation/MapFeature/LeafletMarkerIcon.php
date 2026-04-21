<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;

use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapFeatureBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides marker icon adjustment.
 *
 * @MapFeature(
 *   id = "leaflet_marker_icon",
 *   name = @Translation("Marker Icon Adjustment"),
 *   description = @Translation("Icon properties."),
 *   type = "leaflet",
 * )
 */
class LeafletMarkerIcon extends MapFeatureBase implements ContainerFactoryPluginInterface {

  /**
   * File uri generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * Constructs a new LeafletMarkerIcon plugin object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file url generator.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FileUrlGeneratorInterface $file_url_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('file_url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'marker_icon_path' => '',
      'icon_size' => [
        'width' => NULL,
        'height' => NULL,
      ],
      'icon_anchor' => [
        'x' => NULL,
        'y' => NULL,
      ],
      'popup_anchor' => [
        'x' => 0,
        'y' => 0,
      ],
      'marker_shadow_path' => '',
      'shadow_size' => [
        'width' => NULL,
        'height' => NULL,
      ],
      'shadow_anchor' => [
        'x' => NULL,
        'y' => NULL,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents) {
    $form['marker_icon_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Icon path'),
      '#description' => $this->t('Set relative or absolute path to custom marker icon. Tokens supported. Empty for default. Attention: In views contexts, additional icon source options are available in the style settings.'),
      '#default_value' => $settings['marker_icon_path'],
    ];

    $form['icon_size'] = [
      '#type' => 'item',
      '#description' => $this->t('Size of the icon image in pixels.'),
      'width' => [
        '#type' => 'number',
        '#title' => $this->t('Icon Size - Width'),
        '#default_value' => $settings['icon_size']['width'],
        '#min' => 0,
      ],
      'height' => [
        '#type' => 'number',
        '#title' => $this->t('Icon Size - Height'),
        '#default_value' => $settings['icon_size']['height'],
        '#min' => 0,
      ],
    ];

    $form['icon_anchor'] = [
      '#type' => 'item',
      '#description' => $this->t('The coordinates of the "tip" of the icon (relative to its top left corner). The icon will be aligned so that this point is at the marker\'s geographical location. Centered by default if size is specified.'),
      'x' => [
        '#type' => 'number',
        '#title' => $this->t('Icon Anchor - X'),
        '#default_value' => $settings['icon_anchor']['x'],
      ],
      'y' => [
        '#type' => 'number',
        '#title' => $this->t('Icon Anchor - Y'),
        '#default_value' => $settings['icon_anchor']['y'],
      ],
    ];

    $form['popup_anchor'] = [
      '#type' => 'item',
      '#description' => $this->t('The coordinates of the point from which popups will "open", relative to the icon anchor.'),
      'x' => [
        '#type' => 'number',
        '#title' => $this->t('Popup Anchor - X'),
        '#default_value' => $settings['popup_anchor']['x'],
      ],
      'y' => [
        '#type' => 'number',
        '#title' => $this->t('Popup Anchor - Y'),
        '#default_value' => $settings['popup_anchor']['y'],
      ],
    ];

    $form['marker_shadow_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Shadow path'),
      '#description' => $this->t('Set relative or absolute path to custom marker shadow. Tokens supported. Empty for default. Attention: In views contexts, additional shadow source options are available in the style settings.'),
      '#default_value' => $settings['marker_shadow_path'],
    ];

    $form['shadow_size'] = [
      '#type' => 'item',
      '#description' => $this->t('Size of the shadow image in pixels.'),
      'width' => [
        '#type' => 'number',
        '#title' => $this->t('Shadow Size - Width'),
        '#default_value' => $settings['shadow_size']['width'],
        '#min' => 0,
      ],
      'height' => [
        '#type' => 'number',
        '#title' => $this->t('Shadow Size - Height'),
        '#default_value' => $settings['shadow_size']['height'],
        '#min' => 0,
      ],
    ];

    $form['shadow_anchor'] = [
      '#type' => 'item',
      '#description' => $this->t('The coordinates of the "tip" of the shadow (relative to its top left corner) (the same as iconAnchor if not specified).'),
      'x' => [
        '#type' => 'number',
        '#title' => $this->t('Shadow Anchor - X'),
        '#default_value' => $settings['shadow_anchor']['x'],
      ],
      'y' => [
        '#type' => 'number',
        '#title' => $this->t('Shadow Anchor - Y'),
        '#default_value' => $settings['shadow_anchor']['y'],
      ],
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
                  'iconSize'     => [
                    'width' => (int) $feature_settings['icon_size']['width'],
                    'height' => (int) $feature_settings['icon_size']['height'],
                  ],
                  'iconAnchor'   => [
                    'x' => (int) $feature_settings['icon_anchor']['x'],
                    'y' => (int) $feature_settings['icon_anchor']['y'],
                  ],
                  'popupAnchor'  => [
                    'x' => (int) $feature_settings['popup_anchor']['x'],
                    'y' => (int) $feature_settings['popup_anchor']['y'],
                  ],
                  'shadowSize' => [
                    'width' => (int) $feature_settings['shadow_size']['width'],
                    'height' => (int) $feature_settings['shadow_size']['height'],
                  ],
                  'shadowAnchor' => [
                    'x' => (int) $feature_settings['shadow_anchor']['x'],
                    'y' => (int) $feature_settings['shadow_anchor']['y'],
                  ],
                ],
              ],
            ],
          ],
        ],
      ]
    );

    if (!empty($feature_settings['marker_icon_path'])) {
      $iconPath = \Drupal::token()->replace($feature_settings['marker_icon_path'], $context);
      $iconUrl = $this->fileUrlGenerator->generateString($iconPath);
      $render_array['#attached']['drupalSettings']['geolocation']['maps'][$render_array['#id']][$this->getPluginId()]['markerIconPath'] = $iconUrl;
    }

    if (!empty($feature_settings['marker_shadow_path'])) {
      $shadowPath = \Drupal::token()->replace($feature_settings['marker_shadow_path'], $context);
      $shadowUrl = $this->fileUrlGenerator->generateString($shadowPath);
      $render_array['#attached']['drupalSettings']['geolocation']['maps'][$render_array['#id']][$this->getPluginId()]['markerShadowPath'] = $shadowUrl;
    }

    return $render_array;
  }

}
