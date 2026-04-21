<?php

namespace Drupal\geocoder_field\Plugin\Geocoder\Field;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\geocoder\DumperPluginManager;
use Drupal\geocoder\ProviderPluginManager;
use Drupal\geocoder_field\GeocoderFieldPluginInterface;
use Drupal\geocoder_field\GeocoderFieldPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a default generic geocoder field plugin.
 *
 * @GeocoderField(
 *   id = "default",
 *   label = @Translation("Generic geofield field plugin"),
 *   field_types = {
 *     "text",
 *     "text_long",
 *     "text_with_summary",
 *     "string",
 *     "string_long",
 *   }
 * )
 */
class DefaultField extends PluginBase implements GeocoderFieldPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The plugin manager for this type of plugin.
   *
   * @var \Drupal\geocoder_field\GeocoderFieldPluginManager
   */
  protected $fieldPluginManager;

  /**
   * The dumper plugin manager service.
   *
   * @var \Drupal\geocoder\DumperPluginManager
   */
  protected $dumperPluginManager;

  /**
   * The provider plugin manager service.
   *
   * @var \Drupal\geocoder\ProviderPluginManager
   */
  protected $providerPluginManager;

  /**
   * The Renderer service property.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $renderer;

  /**
   * The Link generator Service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $link;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a 'default' plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\geocoder_field\GeocoderFieldPluginManager $field_plugin_manager
   *   The plugin manager for this type of plugin.
   * @param \Drupal\geocoder\DumperPluginManager $dumper_plugin_manager
   *   The dumper plugin manager service.
   * @param \Drupal\geocoder\ProviderPluginManager $provider_plugin_manager
   *   The provider plugin manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The Link Generator service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    ModuleHandlerInterface $module_handler,
    GeocoderFieldPluginManager $field_plugin_manager,
    DumperPluginManager $dumper_plugin_manager,
    ProviderPluginManager $provider_plugin_manager,
    RendererInterface $renderer,
    LinkGeneratorInterface $link_generator,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->config = $config_factory->get('geocoder.settings');
    $this->moduleHandler = $module_handler;
    $this->fieldPluginManager = $field_plugin_manager;
    $this->dumperPluginManager = $dumper_plugin_manager;
    $this->providerPluginManager = $provider_plugin_manager;
    $this->renderer = $renderer;
    $this->link = $link_generator;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('geocoder_field.plugin.manager.field'),
      $container->get('plugin.manager.geocoder.dumper'),
      $container->get('plugin.manager.geocoder.provider'),
      $container->get('renderer'),
      $container->get('link_generator'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(FieldConfigInterface $field, array $form, FormStateInterface &$form_state) {

    $geocoder_settings_link = $this->link->generate($this->t('Edit options in the Geocoder configuration page</span>'), Url::fromRoute('geocoder.settings', [], [
      'query' => [
        'destination' => Url::fromRoute('<current>')
          ->toString(),
      ],
    ]));

    $element = [
      '#type' => 'details',
      '#title' => $this->t('Geocode'),
      '#open' => TRUE,
    ];

    if ($this->config->get('geocoder_presave_disabled')) {
      $element['#description'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t("<b>The Geocoder and Reverse Geocoding operations are disabled, and won't be processed.</b> (@geocoder_settings_link)", [
          '@geocoder_settings_link' => $geocoder_settings_link,
        ]),
      ];
      $element['#open'] = FALSE;
    }

    // Attach Geocoder Library.
    $element['#attached']['library'] = [
      'geocoder/general',
    ];

    $geocoder_field_unselected_condition = [':input[name="third_party_settings[geocoder_field][method]"]' => ['value' => 'none']];
    $basic_invisible_state_condition = [
      'invisible' => $geocoder_field_unselected_condition,
    ];

    $element['method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Geocode method'),
      '#options' => [
        'none' => $this->t('No geocoding'),
        'geocode' => $this->t('<b>Geocode</b> from an existing field'),
      ],
      '#default_value' => $field->getThirdPartySetting('geocoder_field', 'method', 'none'),
    ];

    $element['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Weight'),
      '#description' => $this->t('This is the weight order that will be followed for Geocode/Reverse Geocode operations on multiple fields of this entity. Lowest weights will be processed first.'),
      '#default_value' => $field->getThirdPartySetting('geocoder_field', 'weight', 0),
      '#min' => 0,
      '#max' => 9,
      '#size' => 2,
      '#states' => $basic_invisible_state_condition,
    ];

    $element['geocode'] = [
      '#type' => 'container',
      '#title' => 'geocode',
      '#states' => [
        'visible' => [
          ':input[name="third_party_settings[geocoder_field][method]"]' => ['value' => 'geocode'],
        ],
      ],
    ];

    $element['reverse_geocode'] = [
      '#type' => 'container',
      '#title' => 'reverse_geocode',
      '#states' => [
        'visible' => [
          ':input[name="third_party_settings[geocoder_field][method]"]' => ['value' => 'reverse_geocode'],
        ],
      ],
    ];

    // Get the field options for geocode and reverse geocode source fields.
    $geocode_source_fields_options = $this->fieldPluginManager->getGeocodeSourceFields($field->getTargetEntityTypeId(), $field->getTargetBundle(), $field->getName());
    $reverse_geocode_source_fields_options = $this->fieldPluginManager->getReverseGeocodeSourceFields($field->getTargetEntityTypeId(), $field->getTargetBundle(), $field->getName());

    // If there is at least one geocode source field defined from the entity,
    // extend the Form with Geocode Option.
    // (from Geofield) capabilities.
    if (!empty($geocode_source_fields_options)) {
      $element['geocode']['field'] = [
        '#type' => 'select',
        '#title' => $this->t('Geocode from an existing field'),
        '#description' => $this->t('Select which field you would like to use as source address field.'),
        '#default_value' => $field->getThirdPartySetting('geocoder_field', 'field'),
        '#options' => $geocode_source_fields_options,
        '#states' => [
          'required' => [
            ':input[name="third_party_settings[geocoder_field][method]"]' => ['value' => 'geocode'],
          ],
        ],
      ];
    }

    // If the Geocoder Geofield Module exists and there is at least one
    // geofield defined from the entity, extend the Form with Reverse Geocode
    // (from Geofield) capabilities.
    if ($this->moduleHandler->moduleExists('geocoder_geofield') && !empty($reverse_geocode_source_fields_options)) {
      // Add the Option to Reverse Geocode.
      $element['method']['#options']['reverse_geocode'] = $this->t('<b>Reverse Geocode</b> from a Geofield type existing field');

      // Add the Element to select the Reverse Geocode field.
      $element['reverse_geocode']['field'] = [
        '#type' => 'select',
        '#title' => $this->t('Reverse Geocode from an existing field'),
        '#description' => $this->t('Select which field you would like to use as geographic source field.'),
        '#default_value' => $field->getThirdPartySetting('geocoder_field', 'field'),
        '#options' => $reverse_geocode_source_fields_options,
        '#states' => [
          'required' => [
            ':input[name="third_party_settings[geocoder_field][method]"]' => ['value' => 'reverse_geocode'],
          ],
        ],
      ];
    }

    $element['skip_not_empty_value'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<b>Skip Geocode/Reverse Geocode</b> if target value is not empty'),
      '#description' => $this->t('This allows to preserve existing value of the target field, and make the Geocoder/Reverse Geocoder work only for insert op'),
      '#default_value' => $field->getThirdPartySetting('geocoder_field', 'skip_not_empty_value', FALSE),
      '#states' => [
        'invisible' => $geocoder_field_unselected_condition,
      ],
    ];

    $element['disabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<strong>Disable</strong> this field in the Content Edit Form'),
      '#description' => $this->t('If checked, the Field will be Disabled to the user in the edit form, </br>and totally managed by the Geocode/Reverse Geocode operation chosen'),
      '#default_value' => $field->getThirdPartySetting('geocoder_field', 'disabled'),
      '#states' => [
        'invisible' => $geocoder_field_unselected_condition,
        'visible' => [':input[name="third_party_settings[geocoder_field][hidden]"]' => ['checked' => FALSE]],
      ],
    ];

    $element['hidden'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<strong>Hide</strong> this field in the Content Edit Form'),
      '#description' => $this->t('If checked, the Field will be Hidden to the user in the edit form, </br>and totally managed by the Geocode/Reverse Geocode operation chosen'),
      '#default_value' => $field->getThirdPartySetting('geocoder_field', 'hidden'),
      '#states' => $basic_invisible_state_condition,
    ];

    // Get the enabled/selected providers.
    $enabled_providers = (array) $field->getThirdPartySetting('geocoder_field', 'providers');

    // Generates the Draggable Table of Selectable Geocoder Plugins.
    $element['providers'] = $this->providerPluginManager->providersPluginsTableList($enabled_providers);
    if (isset($element['providers']['#type'])) {
      $element['providers']['#states'] = $basic_invisible_state_condition;
    }

    $element['dumper'] = [
      '#type' => 'select',
      '#title' => $this->t('Output format'),
      '#default_value' => $field->getThirdPartySetting('geocoder_field', 'dumper', 'wkt'),
      '#options' => $this->dumperPluginManager->getPluginsAsOptions(),
      '#description' => $this->t('Set the output format of the value. Ex, for a geofield, the format must be set to WKT.'),
      '#states' => $basic_invisible_state_condition,
    ];

    $element['geocode']['delta_handling'] = [
      '#type' => 'select',
      '#title' => $this->t('Multi-value input handling'),
      '#description' => $this->t('If the source field is a multi-value field, this is mapped 1-on-1 by default.
      That means that if you can add an unlimited amount of text fields, this also results in an
      unlimited amount of geocodes. However, if you have one field that contains multiple geocodes
      (like a file) you can select single-to-multiple to extract all geocodes from the first field.'),
      '#default_value' => $field->getThirdPartySetting('geocoder_field', 'delta_handling', 'default'),
      '#options' => [
        'default' => $this->t('Match Multiples (default)'),
        's_to_m' => $this->t('Single to Multiple'),
      ],
      '#states' => [
        'visible' => [
          [':input[name="third_party_settings[geocoder_field][method]"]' => ['value' => 'geocode']],
        ],
      ],
    ];

    $failure = (array) $field->getThirdPartySetting('geocoder_field', 'failure') + [
      'handling' => 'preserve',
      'status_message' => TRUE,
      'log' => TRUE,
    ];

    $element['failure']['handling'] = [
      '#type' => 'radios',
      '#title' => $this->t('What to store if geo-coding fails?'),
      '#description' => $this->t('Is possible that the source field cannot be geo-coded. Choose what to store in this field in such case.'),
      '#options' => [
        'preserve' => $this->t('Preserve the existing field value'),
        'empty' => $this->t('Empty the field value'),
      ],
      '#default_value' => $failure['handling'],
      '#states' => $basic_invisible_state_condition,
    ];

    $element['failure']['status_message'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show a status message warning in case of geo-coding failure.'),
      '#default_value' => $failure['status_message'],
      '#states' => $basic_invisible_state_condition,
    ];

    $element['failure']['log'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log the geo-coding failure.'),
      '#default_value' => $failure['log'],
      '#states' => $basic_invisible_state_condition,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateSettingsForm(array $form, FormStateInterface &$form_state) {
    $form_values = $form_state->getValues();

    if ($form_values['method'] !== 'none' && empty($form_values['providers'])) {
      $form_state->setError($form['third_party_settings']['geocoder_field']['providers'], $this->t('The selected Geocode operation needs at least one provider.'));
    }

    // On Reverse Geocode the delta_handling should always be 'default'
    // (many to many), because the other scenario is not possible.
    if ($form_values['method'] === 'reverse_geocode') {
      $form_state->setValue('delta_handling', 'default');
    }

    foreach ($form_values[$form_values['method']] as $key => $value) {
      $form_state->setValue($key, $value);
    }

    $form_state->unsetValue($form_values['method']);
  }

}
