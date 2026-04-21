<?php

namespace Drupal\geolocation\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapCenterManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Map widget base.
 */
abstract class GeolocationMapWidgetBase extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Map Provider ID.
   *
   * @var string
   */
  static protected $mapProviderId = FALSE;

  /**
   * Map Provider Settings Form ID.
   *
   * @var string
   */
  static protected $mapProviderSettingsFormId = 'map_settings';

  /**
   * Map Provider.
   *
   * @var \Drupal\geolocation\MapProviderInterface
   */
  protected $mapProvider = NULL;

  /**
   * Map center manager.
   *
   * @var \Drupal\geolocation\MapCenterManager
   */
  protected $mapCenterManager = NULL;

  /**
   * Constructs a WidgetBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\geolocation\MapCenterManager $map_center_manager
   *   Map center manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityFieldManagerInterface $entity_field_manager, MapCenterManager $map_center_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->entityFieldManager = $entity_field_manager;
    $this->mapCenterManager = $map_center_manager;

    if (!empty(static::$mapProviderId)) {
      $this->mapProvider = \Drupal::service('plugin.manager.geolocation.mapprovider')->getMapProvider(static::$mapProviderId);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.geolocation.mapcenter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function flagErrors(FieldItemListInterface $items, ConstraintViolationListInterface $violations, array $form, FormStateInterface $form_state) {
    foreach ($violations as $violation) {
      if ($violation->getMessageTemplate() == 'This value should not be null.') {
        $form_state->setErrorByName($items->getName(), $this->t('No location has been selected yet for required field %field.', ['%field' => $items->getFieldDefinition()->getLabel()]));
      }
    }
    parent::flagErrors($items, $violations, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = [
      'centre' => [
        'fit_bounds' => [
          'enable' => TRUE,
        ],
      ],
      'auto_client_location' => FALSE,
      'auto_client_location_marker' => FALSE,
      'allow_override_map_settings' => FALSE,
      'hide_textfield_form' => FALSE,
    ];
    $settings[static::$mapProviderSettingsFormId] = \Drupal::service('plugin.manager.geolocation.mapprovider')->getMapProviderDefaultSettings(static::$mapProviderId);
    $settings += parent::defaultSettings();

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings() {
    $settings = parent::getSettings();
    $map_settings = [];
    if (!empty($settings[static::$mapProviderSettingsFormId])) {
      $map_settings = $settings[static::$mapProviderSettingsFormId];
    }

    $settings = NestedArray::mergeDeep(
      $settings,
      [
        static::$mapProviderSettingsFormId => $this->mapProvider->getSettings($map_settings),
      ]
    );

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $settings = $this->getSettings();
    $element = [];

    $element['centre'] = $this->mapCenterManager->getCenterOptionsForm((array) $settings['centre'], ['widget' => $this]);

    $element['auto_client_location_marker'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically set marker to client location if available.'),
      '#default_value' => $settings['auto_client_location_marker'],
    ];

    $element['allow_override_map_settings'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow override the map settings when create/edit an content.'),
      '#default_value' => $settings['allow_override_map_settings'],
    ];

    $element['hide_textfield_form'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide textfields.'),
      '#default_value' => $settings['hide_textfield_form'],
    ];

    if ($this->mapProvider) {
      $element[static::$mapProviderSettingsFormId] = $this->mapProvider->getSettingsForm(
        $settings[static::$mapProviderSettingsFormId],
        [
          'fields',
          $this->fieldDefinition->getName(),
          'settings_edit_form',
          'settings',
          static::$mapProviderSettingsFormId,
        ]
      );
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $settings = $this->getSettings();

    if (!empty($settings['auto_client_location_marker'])) {
      $summary[] = $this->t('Will set client location marker automatically by default');
    }

    if (!empty($settings['allow_override_map_settings'])) {
      $summary[] = $this->t('Users will be allowed to override the map settings for each content.');
    }

    $map_provider_settings = empty($settings[static::$mapProviderSettingsFormId]) ? [] : $settings[static::$mapProviderSettingsFormId];

    $summary = array_replace_recursive($summary, $this->mapProvider->getSettingsSummary($map_provider_settings));

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $default_field_values = FALSE;

    if (!empty($this->fieldDefinition->getDefaultValueLiteral()[$delta])) {
      $default_field_values = [
        'lat' => $this->fieldDefinition->getDefaultValueLiteral()[$delta]['lat'],
        'lng' => $this->fieldDefinition->getDefaultValueLiteral()[$delta]['lng'],
      ];
    }

    // '0' is an allowed value, '' is not.
    if (
      isset($items[$delta]->lat)
      && isset($items[$delta]->lng)
    ) {
      $default_field_values = [
        'lat' => $items[$delta]->lat,
        'lng' => $items[$delta]->lng,
      ];
    }

    $element = [
      '#type' => 'geolocation_input',
      '#title' => $element['#title'] ?? '',
      '#title_display' => $element['#title_display'] ?? '',
      '#description' => $element['#description'] ?? '',
      '#attributes' => [
        'class' => [
          'geolocation-widget-input',
          'geolocation-widget-input-' . $delta,
        ],
        'data-geolocation-widget-input-delta' => $delta,
      ],
    ];

    if ($default_field_values) {
      $element['#default_value'] = [
        'lat' => $default_field_values['lat'],
        'lng' => $default_field_values['lng'],
      ];
    }

    if (
      $delta == 0
      && $this->getSetting('allow_override_map_settings')
      && $this->mapProvider
      // Hide on default value config settings form.
      && !(!empty($form_state->getBuildInfo()['base_form_id']) && $form_state->getBuildInfo()['base_form_id'] == 'field_config_form')
    ) {
      $overriden_map_settings = empty($this->getSetting(static::$mapProviderSettingsFormId)) ? [] : $this->getSetting(static::$mapProviderSettingsFormId);

      if (!empty($items->get(0)->getValue()['data']['map_provider_settings'])) {
        $overriden_map_settings = $items->get(0)->getValue()['data']['map_provider_settings'];
      }

      $element[static::$mapProviderSettingsFormId] = $this->mapProvider->getSettingsForm(
        $overriden_map_settings,
        [
          $this->fieldDefinition->getName(),
          0,
          static::$mapProviderSettingsFormId,
        ]
      );
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    $element = parent::form($items, $form, $form_state, $get_delta);

    $settings = $this->getSettings();
    $id = Html::getUniqueId('edit_' . $this->fieldDefinition->getName() . '_wrapper');

    if (empty($element['#attributes'])) {
      $element['#attributes'] = [];
    }

    $element['#attributes'] = array_merge_recursive(
      $element['#attributes'],
      [
        'data-widget-type' => $this->getPluginId(),
        'id' => $id,
        'class' => [
          'geolocation-map-widget',
        ],
      ]
    );

    if (empty($element['#attached'])) {
      $element['#attached'] = [];
    }

    $element['#attached'] = BubbleableMetadata::mergeAttachments(
      $element['#attached'],
      [
        'library' => [
          'geolocation/geolocation.widget.map',
        ],
        'drupalSettings' => [
          'geolocation' => [
            'widgetSettings' => [
              $id => [
                'autoClientLocationMarker' => $settings['auto_client_location_marker'] ? TRUE : FALSE,
                'cardinality' => $this->fieldDefinition->getFieldStorageDefinition()->getCardinality(),
                'fieldName' => $this->fieldDefinition->getName(),
              ],
            ],
          ],
        ],
      ]
    );

    if ($settings['hide_textfield_form']) {
      if ($element['widget']['#cardinality_multiple']) {
        if (empty($element['widget']['#attributes'])) {
          $element['widget']['#attributes'] = [];
        }

        $element['widget']['#attributes'] = array_merge_recursive(
          $element['widget']['#attributes'],
          [
            'class' => [
              'visually-hidden',
            ],
          ]
        );
      }
      else {
        if (!empty($element['widget'][0])) {
          $element['widget'][0]['#attributes'] = array_merge_recursive(
            $element['widget'][0]['#attributes'],
            [
              'class' => [
                'visually-hidden',
              ],
            ]
          );
        }
      }
    }

    $element['map'] = [
      '#type' => 'geolocation_map',
      '#weight' => -10,
      '#settings' => $settings[static::$mapProviderSettingsFormId],
      '#id' => $id . '-map',
      '#maptype' => static::$mapProviderId,
      '#context' => ['widget' => $this],
    ];

    $element['map'] = $this->mapCenterManager->alterMap($element['map'], $settings['centre']);

    if (
      $this->getSetting('allow_override_map_settings')
      && !empty($items->get(0)->getValue()['data']['map_provider_settings'])
    ) {
      $element['map']['#settings'] = $items->get(0)->getValue()['data']['map_provider_settings'];
    }

    $context = [
      'widget' => $this,
      'form_state' => $form_state,
      'field_definition' => $this->fieldDefinition,
    ];

    if (!$this->isDefaultValueWidget($form_state)) {
      \Drupal::moduleHandler()->alter('geolocation_field_map_widget', $element, $context);
    }

    return $element;
  }

  /**
   * Return map provider.
   *
   * @return bool|\Drupal\geolocation\MapProviderInterface
   *   Map provder or false.
   */
  public function getMapProvider() {
    if ($this->mapProvider) {
      return $this->mapProvider;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $values = parent::massageFormValues($values, $form, $form_state);

    if (!empty($this->settings['allow_override_map_settings'])) {
      if (!empty($values[0][static::$mapProviderSettingsFormId])) {
        $values[0]['data']['map_provider_settings'] = $values[0][static::$mapProviderSettingsFormId];
        unset($values[0][static::$mapProviderSettingsFormId]);
      }
    }

    return $values;
  }

}
