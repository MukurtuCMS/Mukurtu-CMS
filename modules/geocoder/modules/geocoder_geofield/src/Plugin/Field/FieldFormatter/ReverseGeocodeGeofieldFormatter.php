<?php

namespace Drupal\geocoder_geofield\Plugin\Field\FieldFormatter;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\geocoder\DumperPluginManager;
use Drupal\geocoder\GeocoderInterface;
use Drupal\geocoder\ProviderPluginManager;
use Drupal\geocoder_field\Plugin\Field\FieldFormatter\GeocodeFormatter;
use Drupal\geofield\GeoPHP\GeoPHPInterface;
use Geocoder\Model\AddressCollection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the Geocode formatter.
 *
 * @FieldFormatter(
 *   id = "geocoder_geofield_reverse_geocode",
 *   label = @Translation("Reverse geocode"),
 *   field_types = {
 *     "geofield",
 *   }
 * )
 */
class ReverseGeocodeGeofieldFormatter extends GeocodeFormatter {

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
      $container->get('geofield.geophp')
    );
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
        /** @var \Geometry $geom */
        $geom = $this->geoPhpWrapper->load($item->value);

        /** @var \Point $centroid */
        $centroid = $geom->getCentroid();

        if ($address_collection = $this->geocoder->reverse($centroid->y(), $centroid->x(), $providers)) {
          $elements[$delta] = [
            '#markup' => $address_collection instanceof AddressCollection && !$address_collection->isEmpty() ? $dumper->dump($address_collection->first()) : "",
          ];
        }
      }
    }
    catch (PluginException $e) {
      $this->getLogger('geocoder')->error($e->getMessage());
    }
    return $elements;
  }

}
