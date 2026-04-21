<?php

declare(strict_types=1);

namespace Drupal\geocoder\Entity;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\LazyPluginCollection;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\geocoder\GeocoderProviderInterface;
use Drupal\geocoder\Plugin\GeocoderProviderPluginCollection;
use Drupal\geocoder\ProviderInterface;

/**
 * Defines the Geocoder provider entity type.
 *
 * This entity wraps a Geocoder provider plugin and supplies the configuration
 * for it.
 *
 * @ConfigEntityType(
 *   id = "geocoder_provider",
 *   label = @Translation("Geocoder provider"),
 *   label_collection = @Translation("Geocoder providers"),
 *   label_singular = @Translation("geocoder provider"),
 *   label_plural = @Translation("geocoder providers"),
 *   label_count = @PluralTranslation(
 *     singular = "@count geocoder provider",
 *     plural = "@count geocoder providers",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\geocoder\GeocoderProviderListBuilder",
 *     "form" = {
 *       "add" = "Drupal\geocoder\Form\GeocoderProviderAddForm",
 *       "edit" = "Drupal\geocoder\Form\GeocoderProviderEditForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "geocoder_provider",
 *   admin_permission = "administer site configuration",
 *   links = {
 *     "collection" = "/admin/config/system/geocoder/geocoder-provider",
 *     "edit-form" = "/admin/config/system/geocoder/geocoder-provider/manage/{geocoder_provider}",
 *     "delete-form" = "/admin/config/system/geocoder/geocoder-provider/manage/{geocoder_provider}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "plugin",
 *     "configuration",
 *   }
 * )
 */
class GeocoderProvider extends ConfigEntityBase implements GeocoderProviderInterface, EntityWithPluginCollectionInterface {

  /**
   * The ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The label.
   *
   * @var string
   */
  protected $label;

  /**
   * The configuration of the provider.
   *
   * @var array
   */
  protected $configuration = [];

  /**
   * The plugin ID of the Geocoder provider.
   *
   * @var string
   */
  protected $plugin;

  /**
   * The plugin collection that stores action plugins.
   *
   * @var \Drupal\Core\Action\ActionPluginCollection
   */
  protected $pluginCollection;

  /**
   * Encapsulates the creation of the action's LazyPluginCollection.
   *
   * @return \Drupal\Component\Plugin\LazyPluginCollection
   *   The action's plugin collection.
   */
  protected function getPluginCollection(): LazyPluginCollection {
    if (!$this->pluginCollection) {
      $this->pluginCollection = new GeocoderProviderPluginCollection(\Drupal::service('plugin.manager.geocoder.provider'), $this->plugin, $this->configuration);
    }
    return $this->pluginCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections(): array {
    return ['configuration' => $this->getPluginCollection()];
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin(): ProviderInterface {
    return $this->getPluginCollection()->get($this->plugin);
  }

  /**
   * {@inheritdoc}
   */
  public function setPlugin(string $plugin): GeocoderProviderInterface {
    $this->plugin = $plugin;
    $this->getPluginCollection()->addInstanceId($plugin);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition(): array {
    /** @var \Drupal\Component\Plugin\PluginBase $plugin */
    $plugin = $this->getPlugin();
    return $plugin->getPluginDefinition() ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function isConfigurable(): bool {
    return $this->getPlugin() instanceof ConfigurableInterface;
  }

}
