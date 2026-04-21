<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\MapFeature;

use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapFeatureBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Google Maps.
 *
 * @MapFeature(
 *   id = "marker_icon",
 *   name = @Translation("Marker Icon Adjustment"),
 *   description = @Translation("Icon properties."),
 *   type = "google_maps",
 * )
 */
class MarkerIcon extends MapFeatureBase implements ContainerFactoryPluginInterface {

  /**
   * File URL Generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

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
      'anchor' => [
        'x' => 0,
        'y' => 0,
      ],
      'origin' => [
        'x' => 0,
        'y' => 0,
      ],
      'label_origin' => [
        'x' => 0,
        'y' => 0,
      ],
      'size' => [
        'width' => NULL,
        'height' => NULL,
      ],
      'scaled_size' => [
        'width' => NULL,
        'height' => NULL,
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

    $form['anchor'] = [
      '#type' => 'item',
      '#description' => $this->t('The position at which to anchor an image in correspondence to the location of the marker on the map. By default, the anchor is located along the center point of the bottom of the image.'),
      'x' => [
        '#type' => 'number',
        '#title' => $this->t('Anchor - X'),
        '#default_value' => $settings['anchor']['x'],
      ],
      'y' => [
        '#type' => 'number',
        '#title' => $this->t('Anchor - Y'),
        '#default_value' => $settings['anchor']['y'],
      ],
    ];
    $form['origin'] = [
      '#type' => 'item',
      '#description' => $this->t('The position of the image within a sprite, if any. By default, the origin is located at the top left corner of the image (0, 0).'),
      'x' => [
        '#type' => 'number',
        '#title' => $this->t('Origin - X'),
        '#default_value' => $settings['origin']['x'],
      ],
      'y' => [
        '#type' => 'number',
        '#title' => $this->t('Origin - Y'),
        '#default_value' => $settings['origin']['y'],
      ],
    ];
    $form['label_origin'] = [
      '#type' => 'item',
      '#description' => $this->t('The origin of the label relative to the top-left corner of the icon image, if a label is supplied by the marker. By default, the origin is located in the center point of the image.'),
      'x' => [
        '#type' => 'number',
        '#title' => $this->t('Label Origin - X'),
        '#default_value' => $settings['label_origin']['x'],
      ],
      'y' => [
        '#type' => 'number',
        '#title' => $this->t('Label Origin - Y'),
        '#default_value' => $settings['label_origin']['y'],
      ],
    ];
    $form['size'] = [
      '#type' => 'item',
      '#description' => $this->t('The display size of the sprite or image. When using sprites, you must specify the sprite size. If the size is not provided, it will be set when the image loads.'),
      'width' => [
        '#type' => 'number',
        '#title' => $this->t('Size - Width'),
        '#default_value' => $settings['size']['width'],
        '#min' => 0,
      ],
      'height' => [
        '#type' => 'number',
        '#title' => $this->t('Size - Height'),
        '#default_value' => $settings['size']['height'],
        '#min' => 0,
      ],
    ];
    $form['scaled_size'] = [
      '#type' => 'item',
      '#description' => $this->t('The size of the entire image after scaling, if any. Use this property to stretch/shrink an image or a sprite.'),
      'width' => [
        '#type' => 'number',
        '#title' => $this->t('Scaled Size - Width'),
        '#default_value' => $settings['scaled_size']['width'],
        '#min' => 0,
      ],
      'height' => [
        '#type' => 'number',
        '#title' => $this->t('Scaled Size - Height'),
        '#default_value' => $settings['scaled_size']['height'],
        '#min' => 0,
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
          'geolocation_google_maps/mapfeature.' . $this->getPluginId(),
        ],
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $render_array['#id'] => [
                $this->getPluginId() => [
                  'enable' => TRUE,
                  'anchor' => $feature_settings['anchor'],
                  'size' => $feature_settings['size'],
                  'scaledSize' => $feature_settings['scaled_size'],
                  'labelOrigin' => $feature_settings['label_origin'],
                  'origin' => $feature_settings['origin'],
                ],
              ],
            ],
          ],
        ],
      ]
    );

    if (!empty($feature_settings['marker_icon_path'])) {
      $path = \Drupal::token()->replace($feature_settings['marker_icon_path'], $context);
      $path = $this->fileUrlGenerator->generateAbsoluteString($path);
      $render_array['#attached']['drupalSettings']['geolocation']['maps'][$render_array['#id']][$this->getPluginId()]['markerIconPath'] = $path;

      if (!empty($render_array['#children']['locations'])) {
        foreach ($render_array['#children']['locations'] as &$location) {
          if (empty($location['#icon'])) {
            $location['#icon'] = $path;
          }
        }
      }
    }

    return $render_array;
  }

}
