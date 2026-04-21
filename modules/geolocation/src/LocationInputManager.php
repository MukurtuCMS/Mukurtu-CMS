<?php

namespace Drupal\geolocation;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Search plugin manager.
 */
class LocationInputManager extends DefaultPluginManager {

  use StringTranslationTrait;

  /**
   * Constructs an LocationInputManager object.
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
    parent::__construct('Plugin/geolocation/LocationInput', $namespaces, $module_handler, 'Drupal\geolocation\LocationInputInterface', 'Drupal\geolocation\Annotation\LocationInput');
    $this->alterInfo('geolocation_locationinput_info');
    $this->setCacheBackend($cache_backend, 'geolocation_locationinput');
  }

  /**
   * Return LocationInput by ID.
   *
   * @param string $id
   *   LocationInput ID.
   * @param array $configuration
   *   Configuration.
   *
   * @return \Drupal\geolocation\LocationInputInterface|false
   *   LocationInput instance.
   */
  public function getLocationInputPlugin($id, array $configuration = []) {
    if (!$this->hasDefinition($id)) {
      return FALSE;
    }
    try {
      /** @var \Drupal\geolocation\LocationInputInterface $instance */
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
  public function getOptionsForm(array $settings, $context = NULL) {
    $form = [
      '#type' => 'table',
      '#prefix' => $this->t('<h3>Location input</h3>Each option will, if it can be applied, supersede any following option.'),
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

    foreach ($this->getDefinitions() as $location_input_id => $location_input_definition) {
      /** @var \Drupal\geolocation\LocationInputInterface $location_input */
      $location_input = $this->createInstance($location_input_id);
      foreach ($location_input->getAvailableLocationInputOptions($context) as $option_id => $label) {
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
          'location_input_id' => [
            '#type' => 'value',
            '#value' => $location_input_id,
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

        $location_input_settings = [];
        if (!empty($settings[$option_id]['settings'])) {
          $location_input_settings = $settings[$option_id]['settings'];
        }
        $option_form = $location_input->getSettingsForm(
          $option_id,
          $location_input->getSettings($location_input_settings),
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
   * Get coordinates.
   *
   * @param array $form_values
   *   Form values.
   * @param array $settings
   *   Option settings.
   * @param mixed $context
   *   Context.
   *
   * @return array
   *   Centre value.
   */
  public function getCoordinates(array $form_values, array $settings, $context = NULL) {
    $coordinates = [];

    foreach ($settings as $option_id => $option) {
      // Ignore if not enabled.
      if (empty($option['enable'])) {
        continue;
      }

      if (!$this->hasDefinition($option['location_input_id'])) {
        continue;
      }

      /** @var \Drupal\geolocation\LocationInputInterface $location_input_plugin */
      $location_input_plugin = $this->createInstance($option['location_input_id']);
      if (empty($option['settings'])) {
        $option['settings'] = [];
      }
      $plugin_coordinates = $location_input_plugin->getCoordinates($form_values, $option_id, $location_input_plugin->getSettings($option['settings']), $context);

      if (!empty($plugin_coordinates)) {
        // Break on first found coordinates.
        return $plugin_coordinates;
      }
    }

    return $coordinates;
  }

  /**
   * Alter output.
   *
   * @param array $settings
   *   Option settings.
   * @param mixed $context
   *   Context.
   * @param array|null $default_value
   *   Form values.
   *
   * @return array
   *   Centre value.
   */
  public function getForm(array $settings, $context = NULL, array $default_value = NULL) {
    $form = [];

    /*
     * Centre handling.
     */
    foreach ($settings as $option_id => $option) {
      // Ignore if not enabled.
      if (empty($option['enable'])) {
        continue;
      }

      if (!$this->hasDefinition($option['location_input_id'])) {
        continue;
      }

      /** @var \Drupal\geolocation\LocationInputInterface $location_input_plugin */
      $location_input_plugin = $this->createInstance($option['location_input_id']);
      $plugin_form = $location_input_plugin->getForm($option_id, empty($option['settings']) ? [] : $option['settings'], $context, $default_value);
      if ($plugin_form) {
        $form = NestedArray::mergeDeep($plugin_form, $form);
      }
    }

    return $form;
  }

}
