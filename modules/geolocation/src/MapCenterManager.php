<?php

namespace Drupal\geolocation;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Search plugin manager.
 */
class MapCenterManager extends DefaultPluginManager {

  use StringTranslationTrait;

  /**
   * Constructs an MapCenterManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/geolocation/MapCenter', $namespaces, $module_handler, 'Drupal\geolocation\MapCenterInterface', 'Drupal\geolocation\Annotation\MapCenter');
    $this->alterInfo('geolocation_mapcenter_info');
    $this->setCacheBackend($cache_backend, 'geolocation_mapcenter');
  }

  /**
   * Return MapCenter by ID.
   *
   * @param string $id
   *   MapCenter ID.
   * @param array $configuration
   *   Configuration.
   *
   * @return \Drupal\geolocation\MapCenterInterface|false
   *   MapCenter instance.
   */
  public function getMapCenter($id, array $configuration = []) {
    if (!$this->hasDefinition($id)) {
      return FALSE;
    }
    try {
      /** @var \Drupal\geolocation\MapCenterInterface $instance */
      $instance = $this->createInstance($id, $configuration);
      if ($instance) {
        return $instance;
      }
    }
    catch (\Exception $e) {
      return FALSE;
    }
    return FALSE;
  }

  /**
   * Get form render array.
   *
   * @param array $settings
   *   Settings.
   * @param mixed $context
   *   Optional context.
   *
   * @return array
   *   Form.
   */
  public function getCenterOptionsForm(array $settings, $context = NULL) {
    $form = [
      '#type' => 'table',
      '#prefix' => $this->t('<h3>Centre override</h3>These options allow to override the default map centre. Each option will, if it can be applied, supersede any following option.'),
      '#header' => [
        [
          'data' => $this->t('Enable'),
          'colspan' => 2,
        ],
        $this->t('Option'),
        $this->t('Settings'),
        $this->t('Weight'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'geolocation-centre-option-weight',
        ],
      ],
    ];

    foreach ($this->getDefinitions() as $map_center_id => $map_center_definition) {
      /** @var \Drupal\geolocation\MapCenterInterface $map_center */
      $map_center = $this->createInstance($map_center_id);
      foreach ($map_center->getAvailableMapCenterOptions($context) as $option_id => $label) {
        $option_enable_id = HTML::getUniqueId($option_id . '_enabled');
        $weight = $settings[$option_id]['weight'] ?? 0;

        $form[$option_id] = [
          '#weight' => $weight,
          '#attributes' => [
            'class' => [
              'draggable',
            ],
          ],
          'enable' => [
            '#attributes' => [
              'id' => $option_enable_id,
            ],
            '#type' => 'checkbox',
            '#default_value' => $settings[$option_id]['enable'] ?? FALSE,
          ],
          'map_center_id' => [
            '#type' => 'value',
            '#value' => $map_center_id,
          ],
          'option' => [
            '#markup' => $label,
          ],
          'settings' => [
            '#markup' => '',
          ],
          'weight' => [
            '#type' => 'weight',
            '#title' => $this->t('Weight for @option', ['@option' => $label]),
            '#title_display' => 'invisible',
            '#size' => 4,
            '#default_value' => $weight,
            '#attributes' => ['class' => ['geolocation-centre-option-weight']],
          ],
        ];

        $map_center_settings = [];
        if (!empty($settings[$option_id]['settings'])) {
          $map_center_settings = $settings[$option_id]['settings'];
        }
        $option_form = $map_center->getSettingsForm(
          $option_id,
          $map_center->getSettings($map_center_settings),
          $context
        );

        if (!empty($option_form)) {
          $option_form['#states'] = [
            'visible' => [
              ':input[id="' . $option_enable_id . '"]' => ['checked' => TRUE],
            ],
          ];
          $option_form['#type'] = 'item';

          $form[$option_id]['settings'] = $option_form;
        }
      }
    }

    uasort($form, [SortArray::class, 'sortByWeightProperty']);

    return $form;
  }

  /**
   * Alter map element.
   *
   * @param array $map
   *   Map render array.
   * @param array $settings
   *   Center option settings.
   * @param mixed $context
   *   Context.
   *
   * @return array
   *   Altered map render array.
   */
  public function alterMap(array $map, array $settings, $context = NULL) {
    $map = array_replace_recursive($map, [
      '#attached' => [
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $map['#id'] => [
                'map_center' => [],
              ],
            ],
          ],
        ],
      ],
    ]);

    /*
     * Centre handling.
     */
    foreach ($settings as $option_id => $option) {
      if (!empty($map['#centre'])) {
        continue;
      }

      // Ignore if not enabled.
      if (empty($option['enable'])) {
        continue;
      }

      // Compatibility to v1.
      if (empty($option['map_center_id'])) {
        $option['map_center_id'] = $option_id;
      }

      if (!$this->hasDefinition($option['map_center_id'])) {
        continue;
      }

      if (empty($option['weight'])) {
        $option['weight'] = 1;
      }

      /** @var \Drupal\geolocation\MapCenterInterface $map_center_plugin */
      $map_center_plugin = $this->createInstance($option['map_center_id']);
      $map_center_plugin_settings = $option['settings'] ?? [];
      $map_center_plugin_settings = $map_center_plugin->getSettings($map_center_plugin_settings);
      $map['#attached']['drupalSettings']['geolocation']['maps'][$map['#id']]['map_center'][$option['map_center_id']] = [
        'map_center_id' => $option['map_center_id'],
        'option_id' => $option_id,
        'settings' => $map_center_plugin_settings,
        'weight' => $option['weight'],
      ];
      $map = $map_center_plugin->alterMap($map, $option_id, $map_center_plugin_settings, $context);
    }

    if (empty($map['#centre'])) {
      $map['#centre'] = ['lat' => 0, 'lng' => 0];
    }

    return $map;
  }

}
