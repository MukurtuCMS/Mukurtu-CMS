<?php

namespace Drupal\geolocation\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\filter\Entity\FilterFormat;

/**
 * Plugin base for Map based formatters.
 */
abstract class GeolocationMapFormatterBase extends FormatterBase {

  /**
   * Map Provider.
   *
   * @var \Drupal\geolocation\MapProviderInterface
   */
  protected $mapProvider = NULL;

  /**
   * Map Provider.
   *
   * @var \Drupal\geolocation\MapProviderManager
   */
  protected $mapProviderManager = NULL;

  /**
   * Data provider ID.
   *
   * @var string
   */
  static protected $dataProviderId = 'geolocation_field_provider';

  /**
   * Data Provider.
   *
   * @var \Drupal\geolocation\DataProviderInterface
   */
  protected $dataProvider = NULL;

  /**
   * MapCenter options manager.
   *
   * @var \Drupal\geolocation\MapCenterManager
   */
  protected $mapCenterManager = NULL;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $settings = $this->getSettings();

    $this->mapProviderManager = \Drupal::service('plugin.manager.geolocation.mapprovider');
    $this->mapCenterManager = \Drupal::service('plugin.manager.geolocation.mapcenter');

    if (!empty($settings['map_provider_id'])) {
      $this->mapProvider = $this->mapProviderManager->getMapProvider($settings['map_provider_id'], $settings['map_provider_settings']);
    }

    $this->dataProvider = \Drupal::service('plugin.manager.geolocation.dataprovider')->createInstance(static::$dataProviderId, $settings['data_provider_settings']);
    if (empty($this->dataProvider)) {
      throw new \Exception('Geolocation data provider not found');
    }
    $this->dataProvider->setFieldDefinition($field_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = parent::defaultSettings();
    $settings['title'] = '';
    $settings['set_marker'] = TRUE;
    $settings['show_label'] = FALSE;
    $settings['show_delta_label'] = FALSE;
    $settings['common_map'] = TRUE;
    $settings['data_provider_settings'] = [];
    $settings['map_provider_id'] = '';
    if (\Drupal::moduleHandler()->moduleExists('geolocation_google_maps')) {
      $settings['map_provider_id'] = 'google_maps';
    }
    elseif (\Drupal::moduleHandler()->moduleExists('geolocation_leaflet')) {
      $settings['map_provider_id'] = 'leaflet';
    }
    $settings['centre'] = [
      'fit_bounds' => [
        'enable' => TRUE,
        'weight' => -101,
        'map_center_id' => 'fit_bounds',
        'settings' => [
          'reset_zoom' => TRUE,
        ],
      ],
    ];
    $settings['map_provider_settings'] = [];
    $settings['info_text'] = [
      'value' => '',
      'format' => filter_fallback_format(),
    ];
    $settings['use_overridden_map_settings'] = FALSE;
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $map_provider_options = $this->mapProviderManager->getMapProviderOptions();

    if (empty($map_provider_options)) {
      return [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t("No map provider found."),
      ];
    }

    $settings = $this->getSettings();

    $element = [];

    $data_provider_settings_form = $this->dataProvider->getSettingsForm($settings['data_provider_settings'], []);
    if (!empty($data_provider_settings_form)) {
      $element['data_provider_settings'] = $data_provider_settings_form;
    }

    $element['set_marker'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set map marker'),
      '#default_value' => $settings['set_marker'],
    ];

    $element['show_label'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show   label'),
      '#default_value' => $settings['show_label'],
    ];

    $element['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Marker title'),
      '#description' => $this->t('When the cursor hovers on the marker, this title will be shown as description.'),
      '#default_value' => $settings['title'],
      '#states' => [
        'visible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][set_marker]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $element['info_text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Marker info text'),
      '#description' => $this->t('When the marker is clicked, this text will be shown in a popup above it. Leave blank to not display. Token replacement supported.'),
      '#states' => [
        'visible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][set_marker]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    if (!empty($settings['info_text']['value'])) {
      $element['info_text']['#default_value'] = $settings['info_text']['value'];
    }

    if (!empty($settings['info_text']['format'])) {
      $element['info_text']['#format'] = $settings['info_text']['format'];
    }

    $element['replacement_patterns'] = [
      '#type' => 'details',
      '#title' => 'Replacement patterns',
      '#description' => $this->t('The following replacement patterns are available.'),
      '#states' => [
        'visible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][set_marker]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $element['replacement_patterns']['token_geolocation'] = $this->dataProvider->getTokenHelp();

    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    if (
      $cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
      || $cardinality > 1
    ) {
      $element['common_map'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Display multiple values on a common map'),
        '#description' => $this->t('By default, each value will be displayed in a separate map. Settings this option displays all values on a common map instead. This settings is only useful on multi-value fields.'),
        '#default_value' => $settings['common_map'],
      ];
      $element['show_delta_label'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show item cardinality as marker label'),
        '#description' => $this->t('By default markers will not have labels, if shown on the common map it might be useful for AODA to show cardinality'),
        '#default_value' => $settings['show_delta_label'],
      ];
    }

    $element['centre'] = $this->mapCenterManager->getCenterOptionsForm((array) $settings['centre'], ['formatter' => $this]);

    $element['map_provider_id'] = [
      '#type' => 'select',
      '#options' => $map_provider_options,
      '#title' => $this->t('Map Provider'),
      '#default_value' => $settings['map_provider_id'],
      '#ajax' => [
        'callback' => [
          get_class($this->mapProviderManager), 'addSettingsFormAjax',
        ],
        'wrapper' => 'map-provider-settings',
        'effect' => 'fade',
      ],
    ];

    $element['map_provider_settings'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $this->t("No settings available."),
    ];

    $parents = [
      'fields',
      $this->fieldDefinition->getName(),
      'settings_edit_form',
      'settings',
    ];

    $map_provider_id = NestedArray::getValue($form_state->getUserInput(), array_merge($parents, ['map_provider_id']));
    if (empty($map_provider_id)) {
      $map_provider_id = $settings['map_provider_id'];
    }
    if (empty($map_provider_id)) {
      $map_provider_id = key($map_provider_options);
    }

    $map_provider_settings = NestedArray::getValue($form_state->getUserInput(), array_merge($parents, ['map_provider_settings']));
    if (empty($map_provider_settings)) {
      $map_provider_settings = $settings['map_provider_settings'];
    }

    if (!empty($map_provider_id)) {
      $element['map_provider_settings'] = $this->mapProviderManager
        ->createInstance($map_provider_id, $map_provider_settings)
        ->getSettingsForm(
          $map_provider_settings,
          array_merge($parents, ['map_provider_settings'])
        );
    }

    $element['map_provider_settings'] = array_replace(
      $element['map_provider_settings'],
      [
        '#prefix' => '<div id="map-provider-settings">',
        '#suffix' => '</div>',
      ]
    );

    $element['use_overridden_map_settings'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use custom map settings if provided'),
      '#description' => $this->t('The field map widget optionally allows to define custom map settings to use here.'),
      '#default_value' => $settings['use_overridden_map_settings'],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $settings = $this->getSettings();

    $summary = [];
    $summary[] = $this->t('Marker set: @marker', ['@marker' => $settings['set_marker'] ? $this->t('Yes') : $this->t('No')]);
    if ($settings['set_marker']) {
      $summary[] = $this->t('Marker Title: @type', ['@type' => $settings['title']]);
      if ($settings['show_label']) {
        $summary[] = $this->t('Showing Marker Label');
      }
      if (
        !empty($settings['info_text']['value'])
        && !empty($settings['info_text']['format'])
      ) {
        $summary[] = $this->t('Marker Info Text: @type', [
          '@type' => current(explode(chr(10), wordwrap(check_markup($settings['info_text']['value'], $settings['info_text']['format']), 30))),
        ]);
      }

      if (!empty($settings['common_map'])) {
        $summary[] = $this->t('Common Map Display: Yes');
      }
      if (!empty($settings['show_delta_label'])) {
        $summary[] = $this->t('Show Cardinality as Label: Yes');
      }
    }

    if ($this->mapProvider) {
      $summary = array_replace_recursive($summary, $this->mapProvider->getSettingsSummary($settings['map_provider_settings']));
    }
    else {
      $summary[] = $this->t('Attention: No map provider set!');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    if ($items->count() == 0) {
      return [];
    }

    $elements = [];

    $settings = $this->getSettings();

    $locations = $this->getLocations($items);

    $parent_entity = $items->getEntity();

    $element_pattern = [
      '#type' => 'geolocation_map',
      '#settings' => $settings['map_provider_settings'],
      '#maptype' => $settings['map_provider_id'],
      '#centre' => [],
      '#context' => [
        'formatter' => $this,
      ],
    ];

    if (!empty($parent_entity)) {
      $element_pattern['#context'][$parent_entity->getEntityTypeId()] = $parent_entity;
    }

    if (!empty($settings['common_map'])) {
      $elements = [
        0 => $element_pattern,
      ];
      $elements[0]['#id'] = uniqid("map-");
      foreach ($locations as $delta => $location) {
        if (!empty($settings['show_delta_label'])) {
          $location['#label'] = $delta + 1;
        }
        $elements[0][$delta] = $location;
      }

      $elements[0] = $this->mapCenterManager->alterMap($elements[0], $settings['centre'], ['formatter' => $this]);
    }
    else {
      foreach ($locations as $delta => $location) {
        $elements[$delta] = $element_pattern;
        $elements[$delta]['#id'] = uniqid("map-" . $delta . "-");
        $elements[$delta]['content'] = $location;

        $elements[$delta] = $this->mapCenterManager->alterMap($elements[$delta], $settings['centre'], ['formatter' => $this]);
      }
    }

    if (
      $settings['use_overridden_map_settings']
      && !empty($items->get(0))
      && !empty($items->get(0)->getValue()['data']['map_provider_settings'])
      && is_array($items->get(0)->getValue()['data']['map_provider_settings'])
    ) {
      $map_settings = $this->mapProvider->getSettings($items->get(0)->getValue()['data']['map_provider_settings']);

      if (!empty($settings['common_map'])) {
        $elements[0]['#settings'] = $map_settings;
      }
      else {
        foreach (Element::children($elements) as $delta => $element) {
          $elements[$delta]['#settings'] = $map_settings;
        }
      }
    }

    return $elements;
  }

  /**
   * Get renderable locations from field items.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Field items.
   *
   * @return array
   *   Renderable locations.
   */
  protected function getLocations(FieldItemListInterface $items) {

    $settings = $this->getSettings();

    $locations = [];

    foreach ($items as $delta => $item) {
      foreach ($this->dataProvider->getPositionsFromItem($item) as $item_position) {
        if (empty($item_position)) {
          continue;
        }

        $title = $this->dataProvider->replaceFieldItemTokens($settings['title'], $item);
        if (empty($title)) {
          $title = $item_position['lat'] . ', ' . $item_position['lng'];
        }

        $location = [
          '#type' => 'geolocation_map_location',
          '#title' => $title,
          '#disable_marker' => empty($settings['set_marker']),
          '#coordinates' => [
            'lat' => $item_position['lat'],
            'lng' => $item_position['lng'],
          ],
          '#weight' => $delta,
        ];

        if ($settings['show_label']) {
          $location['#label'] = $title;
        }

        if (
          !empty($settings['info_text']['value'])
          && !empty($settings['info_text']['format'])
        ) {
          $location['content'] = [
            '#type' => 'processed_text',
            '#text' => $this->dataProvider->replaceFieldItemTokens($settings['info_text']['value'], $item),
            '#format' => $settings['info_text']['format'],
          ];
        }

        $locations[] = $location;
      }

      $locations = array_merge($this->dataProvider->getLocationsFromItem($item), $locations);
      $locations = array_merge($this->dataProvider->getShapesFromItem($item), $locations);
    }

    return $locations;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    $settings = $this->getSettings();

    if (!empty($settings['info_text']['format'])) {
      $filter_format = FilterFormat::load($settings['info_text']['format']);
    }

    if (!empty($filter_format)) {
      $dependencies['config'][] = $filter_format->getConfigDependencyName();
    }
    return $dependencies;
  }

}
