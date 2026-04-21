<?php

namespace Drupal\geofield\Plugin\Field\FieldWidget;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\geofield\GeoPHP\GeoPHPInterface;
use Drupal\geofield\Plugin\GeofieldBackendManager;
use Drupal\geofield\WktGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Abstract class for Geofield widgets.
 */
abstract class GeofieldBaseWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  use LoggerChannelTrait;

  /**
   * The Geofield Backend setup for the specific Field definition.
   *
   * @var \Drupal\geofield\Plugin\GeofieldBackendPluginInterface|null
   */
  protected $geofieldBackend = NULL;

  /**
   * The geoPhpWrapper service.
   *
   * @var \Drupal\geofield\GeoPHP\GeoPHPInterface
   */
  protected $geoPhpWrapper;

  /**
   * The WKT format Generator service.
   *
   * @var \Drupal\geofield\WktGeneratorInterface
   */
  protected $wktGenerator;

  /**
   * GeofieldBaseWidget constructor.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\geofield\GeoPHP\GeoPHPInterface $geophp_wrapper
   *   The geoPhpWrapper.
   * @param \Drupal\geofield\WktGeneratorInterface $wkt_generator
   *   The WKT format Generator service.
   * @param \Drupal\geofield\Plugin\GeofieldBackendManager $geofield_backend_manager
   *   The geofieldBackendManager.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    GeoPHPInterface $geophp_wrapper,
    WktGeneratorInterface $wkt_generator,
    ?GeofieldBackendManager $geofield_backend_manager = NULL,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    try {
      if ($geofield_backend_manager instanceof GeofieldBackendManager) {
        $this->geofieldBackend = $geofield_backend_manager->createInstance($field_definition->getSetting("backend"));
      }
    }
    catch (PluginException $e) {
      $this->getLogger('geofield')->error($e->getMessage());
    }

    $this->geoPhpWrapper = $geophp_wrapper;
    $this->wktGenerator = $wkt_generator;
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
      $container->get('geofield.geophp'),
      $container->get('geofield.wkt_generator'),
      $container->get('plugin.manager.geofield_backend')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // Attach Geofield Libraries.
    $element['#attached']['library'] = [
      'geofield/geofield_general',
    ];
    return ['value' => $element];
  }

  /**
   * Return the specific Geofield Backend Value.
   *
   * Falls back into WKT format, in case Geofield Backend undefined.
   *
   * @param mixed|null $value
   *   The data to load.
   *
   * @return mixed|null
   *   The specific backend format value.
   */
  protected function geofieldBackendValue($value) {
    $output = NULL;
    /** @var \Geometry|null $geom */
    if ($this->geofieldBackend && $geom = $this->geoPhpWrapper->load($value)) {
      $output = $this->geofieldBackend->save($geom);
    }
    elseif ($geom = $this->geoPhpWrapper->load($value)) {
      $output = $geom->out('wkt');
    }
    return $output;
  }

}
