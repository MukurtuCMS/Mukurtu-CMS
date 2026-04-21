<?php

namespace Drupal\geocoder_geofield\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\geocoder\DumperPluginManager;
use Drupal\geocoder\GeocoderInterface;
use Drupal\geocoder\ProviderPluginManager;
use Drupal\geocoder_field\Plugin\Field\FieldFormatter\FileGeocodeFormatter;
use Drupal\geocoder_field\PreprocessorPluginManager;
use Drupal\geofield\GeoPHP\GeoPHPInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Abstract implementation of the GeoPhp Wrapper formatter for File fields.
 */
abstract class GeoPhpGeocodeFormatter extends FileGeocodeFormatter {

  /**
   * The geoPhpWrapper service.
   *
   * @var \Drupal\geofield\GeoPHP\GeoPHPInterface
   */
  protected $geoPhpWrapper;

  /**
   * Constructs a GeoPhpGeocodeFormatter object.
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
   * @param \Drupal\geofield\GeoPHP\GeoPHPInterface $geophp_wrapper
   *   The geoPhpWrapper.
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
    GeoPHPInterface $geophp_wrapper,
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
      $entity_type_manager,
      $preprocessor_manager
    );
    $this->geoPhpWrapper = $geophp_wrapper;
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
      $container->get('plugin.manager.geocoder.preprocessor'),
      $container->get('geofield.geophp')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return parent::defaultSettings() + [
      'adapter' => 'wkt',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    $element = parent::settingsForm($form, $form_state);

    $element['adapter'] = [
      '#type' => 'select',
      '#title' => 'Output format',
      '#options' => $this->geoPhpWrapper->getAdapterMap(),
      '#default_value' => $this->getSetting('adapter'),
    ];

    unset($element['dumper']);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $adapters = $this->geoPhpWrapper->getAdapterMap();
    $adapter = $this->getSetting('adapter');
    $summary['intro'] = $this->pluginDefinition['description'];
    $summary += parent::settingsSummary();
    unset($summary['dumper']);
    $summary['adapter'] = $this->t('Output format: @format', [
      '@format' => !empty($adapter) ? $adapters[$adapter] : $this->t('Not set'),
    ]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $adapters = $this->geoPhpWrapper->getAdapterMap();
    $adapter = $this->getSetting('adapter');
    try {
      /** @var \Drupal\geocoder_field\PreprocessorInterface $preprocessor */
      $preprocessor = $this->preprocessorManager->createInstance('file');
      $preprocessor->setField($items)->preprocess();
      $providers = $this->getEnabledGeocoderProviders();
      if (array_key_exists($adapter, $adapters)) {
        foreach ($items as $delta => $item) {
          /** @var \Geometry $collection */
          if ($collection = $this->geocoder->geocode($item->value, $providers)) {
            $elements[$delta] = [
              '#type' => 'html_tag',
              '#tag' => 'code',
              '#value' => \Drupal\Core\Render\Markup::create(
                htmlspecialchars($collection->out($adapter), ENT_QUOTES | ENT_XML1, 'UTF-8')
              ),
            ];
          }
        }
      }
    }
    catch (\Exception $e) {
      $this->getLogger('geocoder')->error($e->getMessage());
    }

    return $elements;
  }

}
