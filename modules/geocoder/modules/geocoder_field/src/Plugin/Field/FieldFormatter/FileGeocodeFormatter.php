<?php

namespace Drupal\geocoder_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\geocoder\DumperPluginManager;
use Drupal\geocoder\Entity\GeocoderProvider;
use Drupal\geocoder\GeocoderInterface;
use Drupal\geocoder\ProviderPluginManager;
use Drupal\geocoder_field\Plugin\Field\GeocodeFormatterBase;
use Drupal\geocoder_field\PreprocessorPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the Geocode formatter for File and Image fields.
 *
 * @FieldFormatter(
 *   id = "geocoder_geocode_formatter_file",
 *   label = @Translation("Geocode File/Image Gps Exif"),
 *   field_types = {
 *     "file",
 *     "image",
 *   },
 *   description =
 *   "Extracts valid GPS Exif data from the file/image (if existing)"
 * )
 */
class FileGeocodeFormatter extends GeocodeFormatterBase {

  /**
   * The Preprocessor Manager.
   *
   * @var \Drupal\geocoder_field\PreprocessorPluginManager
   */
  protected $preprocessorManager;

  /**
   * Unique Geocoder Plugin used by this formatter.
   *
   * @var string
   */
  protected $formatterPlugin = 'file';

  /**
   * Constructs a GeocodeFormatterFile object.
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
   *   The Geocoder service.
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
   * @param \Drupal\geocoder_field\PreprocessorPluginManager $preprocessor_manager
   *   The Preprocessor Manager.
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
    PreprocessorPluginManager $preprocessor_manager,
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $label,
      $view_mode,
      $third_party_settings,
      $geocoder,
      $provider_plugin_manager,
      $dumper_plugin_manager,
      $renderer,
      $link_generator,
      $entity_type_manager
    );
    $this->preprocessorManager = $preprocessor_manager;
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
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.geocoder.preprocessor')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->pluginDefinition['description'],
    ];
    $element += parent::settingsForm($form, $form_state);

    // Filter out the Geocoder Plugins that are not compatible with the Geocode
    // Formatter action.
    $compatible_providers = array_filter($element['providers'], function ($e) {
      $geocoder_providers = $this->geocoderProviders;
      if (isset($geocoder_providers[$e]) && $geocoder_provider = $geocoder_providers[$e]) {
        /** @var \Drupal\geocoder\Entity\GeocoderProvider $geocoder_provider */
        /** @var \Drupal\Component\Plugin\PluginBase $plugin */
        $plugin = $geocoder_provider->getPlugin();
        return $plugin->getPluginId() == $this->formatterPlugin;
      }
      return TRUE;

    }, ARRAY_FILTER_USE_KEY);

    // Generate a warning markup in case of no compatible Geocoder Provider.
    if (count($element['providers']) - count($compatible_providers) == count($this->geocoderProviders)) {
      $element['warning'] = [
        '#markup' => $this->t('Any "@plugin" Geocoder Provider available for this Formatter.', [
          '@plugin' => $this->formatterPlugin,
        ]),
      ];
    }
    $element['providers'] = $compatible_providers;
    return $element;

  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary['intro'] = $this->pluginDefinition['description'];
    $summary += parent::settingsSummary();
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    try {
      /** @var \Drupal\geocoder\DumperInterface $dumper */
      $dumper = $this->dumperPluginManager->createInstance($this->getSetting('dumper'));
      /** @var \Drupal\geocoder_field\PreprocessorInterface $preprocessor */
      $preprocessor = $this->preprocessorManager->createInstance('file');
      $preprocessor->setField($items)->preprocess();
      $providers = $this->getEnabledGeocoderProviders();
      foreach ($items as $delta => $item) {
        if ($address_collection = $this->geocoder->geocode($item->value, $providers)) {
          $elements[$delta] = [
            '#markup' => $dumper->dump($address_collection->first()),
          ];
        }
      }
    }
    catch (\Exception $e) {
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

    $providers = array_filter(parent::getEnabledGeocoderProviders(), function (GeocoderProvider $geocoder_provider) {
      /** @var \Drupal\Component\Plugin\PluginBase $plugin */
      $plugin = $geocoder_provider->getPlugin();
      return $plugin->getPluginId() == $this->formatterPlugin;
    });
    return $providers;
  }

}
