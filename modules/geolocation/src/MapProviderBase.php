<?php

namespace Drupal\geolocation;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provide Map Provider Base class.
 *
 * @package Drupal\geolocation
 */
abstract class MapProviderBase extends PluginBase implements MapProviderInterface, ContainerFactoryPluginInterface {

  /**
   * Map feature manager.
   *
   * @var \Drupal\geolocation\MapFeatureManager
   */
  protected $mapFeatureManager;

  /**
   * Constructs a new GeocoderBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\geolocation\MapFeatureManager $map_feature_manager
   *   Map feature manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MapFeatureManager $map_feature_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->mapFeatureManager = $map_feature_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geolocation.mapfeature')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'map_features' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(array $settings) {
    $default_settings = $this->getDefaultSettings();
    $settings = array_replace_recursive($default_settings, $settings);

    foreach ($settings as $key => $setting) {
      if (!isset($default_settings[$key])) {
        unset($settings[$key]);
      }
    }

    foreach ($this->mapFeatureManager->getMapFeaturesByMapType($this->getPluginId()) as $feature_id => $feature_definition) {
      if (!empty($settings['map_features'][$feature_id]['enabled'])) {
        $feature = $this->mapFeatureManager->getMapFeature($feature_id, []);
        if ($feature) {
          if (empty($settings['map_features'][$feature_id]['settings'])) {
            $settings['map_features'][$feature_id]['settings'] = $feature->getSettings([]);
          }
          else {
            $settings['map_features'][$feature_id]['settings'] = $feature->getSettings($settings['map_features'][$feature_id]['settings']);
          }
        }
        else {
          unset($settings['map_features'][$feature_id]);
        }
      }
    }

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsSummary(array $settings) {
    $summary = [];

    foreach ($this->mapFeatureManager->getMapFeaturesByMapType($this->getPluginId()) as $feature_id => $feature_definition) {
      if (!empty($settings['map_features'][$feature_id]['enabled'])) {
        $feature = $this->mapFeatureManager->getMapFeature($feature_id, []);
        if ($feature) {
          if (!empty($settings['map_features'][$feature_id]['settings'])) {
            $feature_settings = $settings['map_features'][$feature_id]['settings'];
          }
          else {
            $feature_settings = $feature->getSettings([]);
          }
          $summary = array_merge(
            $summary,
            $feature->getSettingsSummary($feature_settings)
          );
        }
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents = []) {
    $form = [
      '#type' => 'details',
      '#title' => $this->t('%map_provider settings', ['%map_provider' => $this->pluginDefinition['name']]),
      '#description' => $this->t('Additional map settings provided by %map_provider', ['%map_provider' => $this->pluginDefinition['name']]),
    ];

    $map_features = $this->mapFeatureManager->getMapFeaturesByMapType($this->getPluginId());

    if (empty($map_features)) {
      return $form;
    }

    $form['map_features'] = [
      '#type' => 'table',
      '#weight' => 100,
      '#prefix' => $this->t('<h3>Map Features</h3>'),
      '#header' => [
        $this->t('Enable'),
        $this->t('Feature'),
        $this->t('Settings'),
        $this->t('Weight'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'geolocation-map-feature-option-weight',
        ],
      ],
    ];
    $form['map_features']['#element_validate'][] = [
      $this, 'validateMapFeatureForms',
    ];

    foreach ($map_features as $feature_id => $feature_definition) {
      $feature = $this->mapFeatureManager->getMapFeature($feature_id, []);
      if (empty($feature)) {
        continue;
      }

      $feature_enable_id = Html::getUniqueId($feature_id . '_enabled');
      $weight = $settings['map_features'][$feature_id]['weight'] ?? 0;

      $feature_settings = $settings['map_features'][$feature_id]['settings'] ?? [];

      $form['map_features'][$feature_id] = [
        '#weight' => $weight,
        '#attributes' => [
          'class' => [
            'draggable',
          ],
        ],
        'enabled' => [
          '#attributes' => [
            'id' => $feature_enable_id,
          ],
          '#type' => 'checkbox',
          '#default_value' => !empty($settings['map_features'][$feature_id]['enabled']),
        ],
        'feature' => [
          '#type' => 'label',
          '#title' => $feature_definition['name'],
          '#suffix' => $feature_definition['description'],
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @option', ['@option' => $feature_definition['name']]),
          '#title_display' => 'invisible',
          '#size' => 4,
          '#default_value' => $weight,
          '#attributes' => ['class' => ['geolocation-map-feature-option-weight']],
        ],
      ];

      $feature_form = $feature->getSettingsForm(
        $feature->getSettings($feature_settings),
        array_merge($parents, ['map_features', $feature_id, 'settings'])
      );

      if (!empty($feature_form)) {
        $feature_form['#states'] = [
          'visible' => [
            ':input[id="' . $feature_enable_id . '"]' => ['checked' => TRUE],
          ],
        ];
        $feature_form['#type'] = 'item';

        $form['map_features'][$feature_id]['settings'] = $feature_form;
      }
    }

    uasort($form['map_features'], [SortArray::class, 'sortByWeightProperty']);

    return $form;
  }

  /**
   * Validate form.
   *
   * @param array $element
   *   Form element to check.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   * @param array $form
   *   Current form.
   */
  public function validateMapFeatureForms(array $element, FormStateInterface $form_state, array $form) {
    $values = $form_state->getValues();

    $parents = [];
    if (!empty($element['#parents'])) {
      $parents = $element['#parents'];
      $values = NestedArray::getValue($values, $parents);
    }

    foreach ($this->mapFeatureManager->getMapFeaturesByMapType($this->getPluginId()) as $feature_id => $feature_definition) {
      if (!empty($values[$feature_id]['enabled'])) {
        $feature = $this->mapFeatureManager->getMapFeature($feature_id, []);
        if ($feature && method_exists($feature, 'validateSettingsForm')) {
          $feature_parents = $parents;
          array_push($feature_parents, $feature_id, 'settings');
          $feature->validateSettingsForm(empty($values[$feature_id]['settings']) ? [] : $values[$feature_id]['settings'], $form_state, $feature_parents);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alterRenderArray(array $render_array, array $map_settings, array $context = []) {

    if (!empty($map_settings['map_features'])) {
      uasort($map_settings['map_features'], '\Drupal\Component\Utility\SortArray::sortByWeightElement');

      foreach ($map_settings['map_features'] as $feature_id => $feature_settings) {
        if (!empty($feature_settings['enabled'])) {
          $feature = $this->mapFeatureManager->getMapFeature($feature_id, []);
          if ($feature) {
            if (empty($feature_settings['settings'])) {
              $feature_settings['settings'] = [];
            }
            $render_array = $feature->alterMap($render_array, $feature->getSettings($feature_settings['settings']), $context);
          }
        }
      }
    }

    return $render_array;
  }

  /**
   * {@inheritdoc}
   */
  public function alterCommonMap(array $render_array, array $map_settings, array $context) {
    return $render_array;
  }

  /**
   * {@inheritdoc}
   */
  public static function getControlPositions() {
    return [];
  }

}
