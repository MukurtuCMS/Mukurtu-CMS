<?php

declare(strict_types=1);

namespace Drupal\geocoder_field\Plugin\Field;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\geocoder\DumperInterface;
use Drupal\geocoder\DumperPluginManager;
use Drupal\geocoder\Entity\GeocoderProvider;
use Drupal\geocoder\GeocoderInterface;
use Drupal\geocoder\ProviderPluginManager;
use Geocoder\Model\AddressCollection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base Plugin implementation of the Geocode formatter.
 */
abstract class GeocodeFormatterBase extends FormatterBase implements ContainerFactoryPluginInterface {

  use LoggerChannelTrait;

  /**
   * The geocoder service.
   *
   * @var \Drupal\geocoder\GeocoderInterface
   */
  protected $geocoder;

  /**
   * The provider plugin manager service.
   *
   * @var \Drupal\geocoder\ProviderPluginManager
   */
  protected $providerPluginManager;

  /**
   * The dumper plugin manager service.
   *
   * @var \Drupal\geocoder\DumperPluginManager
   */
  protected $dumperPluginManager;

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
   * The list of created Geocoder Providers.
   *
   * @var array
   */
  protected $geocoderProviders = [];

  /**
   * Constructs a GeocodeFormatterBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\geocoder\GeocoderInterface $geocoder
   *   The gecoder service.
   * @param \Drupal\geocoder\ProviderPluginManager $provider_plugin_manager
   *   The provider plugin manager service.
   * @param \Drupal\geocoder\DumperPluginManager $dumper_plugin_manager
   *   The dumper plugin manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The Link Generator service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    GeocoderInterface $geocoder,
    ProviderPluginManager $provider_plugin_manager,
    DumperPluginManager $dumper_plugin_manager,
    RendererInterface $renderer,
    LinkGeneratorInterface $link_generator,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->geocoder = $geocoder;
    $this->providerPluginManager = $provider_plugin_manager;
    $this->dumperPluginManager = $dumper_plugin_manager;
    $this->renderer = $renderer;
    $this->link = $link_generator;
    $this->entityTypeManager = $entity_type_manager;
    try {
      $this->geocoderProviders = $this->entityTypeManager->getStorage('geocoder_provider')
        ->loadMultiple();
    }
    catch (\Exception $e) {
      $this->getLogger('geocoder')->error($e->getMessage());
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
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('geocoder'),
      $container->get('plugin.manager.geocoder.provider'),
      $container->get('plugin.manager.geocoder.dumper'),
      $container->get('renderer'),
      $container->get('link_generator'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return parent::defaultSettings() + [
      'dumper' => 'wkt',
      'providers' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $providers = !empty($this->getSetting('providers')) ? $this->getSetting('providers') : [];

    // Attach Geofield Map Library.
    $element['#attached']['library'] = [
      'geocoder/general',
    ];

    // Get the enabled/selected providers.
    $enabled_providers = [];
    foreach ($providers as $provider_id => $provider_settings) {
      if ($provider_settings['checked']) {
        $enabled_providers[] = $provider_id;
      }
    }

    // Generates the Draggable Table of Selectable Geocoder providers.
    $element['providers'] = $this->providerPluginManager->providersPluginsTableList($enabled_providers);

    // Set a validation for the providers' selection.
    $element['providers']['#element_validate'] = [
      [
        get_class($this),
        'validateProvidersSettingsForm',
      ],
    ];

    $element['dumper'] = [
      '#type' => 'select',
      '#weight' => 25,
      '#title' => 'Output format',
      '#default_value' => $this->getSetting('dumper'),
      '#options' => $this->dumperPluginManager->getPluginsAsOptions(),
      '#description' => $this->t('Set the output format of the value. Ex, for a geofield, the format must be set to WKT.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $dumper_plugins = $this->dumperPluginManager->getPluginsAsOptions();
    $dumper_plugin = $this->getSetting('dumper');

    $provider_labels = array_map(function (GeocoderProvider $provider): ?string {
      return $provider->label();
    }, $this->getEnabledGeocoderProviders());

    $summary['providers'] = $this->t('Geocoder providers(s): @provider_ids', [
      '@provider_ids' => !empty($provider_labels) ? implode(', ', $provider_labels) : $this->t('Not set'),
    ]);

    $summary['dumper'] = $this->t('Output format: @format', [
      '@format' => !empty($dumper_plugin) ? $dumper_plugins[$dumper_plugin] : $this->t('Not set'),
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    try {
      $dumper = $this->dumperPluginManager->createInstance($this->getSetting('dumper'));
      $providers = $this->getEnabledGeocoderProviders();

      foreach ($items as $delta => $item) {
        if ($address_collection = $this->geocoder->geocode($item->value, $providers)) {
          $elements[$delta] = [
            '#markup' => $address_collection instanceof AddressCollection && !$address_collection->isEmpty() && $dumper instanceof DumperInterface ? $dumper->dump($address_collection->first()) : "",
          ];
        }
      }
    }
    catch (PluginException $e) {
      $this->getLogger('geocoder')->error($e->getMessage());
    }
    return $elements;
  }

  /**
   * Returns the Geocoder providers that are enabled in this formatter.
   *
   * @return \Drupal\geocoder\Entity\GeocoderProvider[]
   *   The enabled Geocoder providers, sorted by weight.
   */
  public function getEnabledGeocoderProviders(): array {
    $formatter_settings = $this->getSetting('providers');

    // Filter out all providers that are disabled.
    $providers = array_filter($this->geocoderProviders, function (GeocoderProvider $provider) use ($formatter_settings): bool {
      return !empty($formatter_settings[$provider->id()]) && $formatter_settings[$provider->id()]['checked'] == TRUE;
    });

    // Sort providers according to weight.
    uasort($providers, function (GeocoderProvider $a, GeocoderProvider $b) use ($formatter_settings): int {
      if ((int) $formatter_settings[$a->id()]['weight'] === (int) $formatter_settings[$b->id()]['weight']) {
        return 0;
      }
      return (int) $formatter_settings[$a->id()]['weight'] < (int) $formatter_settings[$b->id()]['weight'] ? -1 : 1;
    });

    return $providers;
  }

  /**
   * Returns the list of created Geocoder Providers.
   *
   * @return \Drupal\geocoder\Entity\GeocoderProvider[]
   *   The list of created Geocoder Providers.
   */
  public function getGeocoderProviders(): array {
    return $this->geocoderProviders;
  }

  /**
   * Validates the providers' selection.
   *
   * @param array $element
   *   The form API form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public static function validateProvidersSettingsForm(array $element, FormStateInterface $form_state) {
    $providers = !empty($element['#value']) ? array_filter($element['#value'], function ($value) {
      return isset($value['checked']) && TRUE == $value['checked'];
    }) : [];

    if (empty($providers)) {
      $form_state->setError($element, t('The selected Geocode operation needs at least one provider.'));
    }
  }

}
