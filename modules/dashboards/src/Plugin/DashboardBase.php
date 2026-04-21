<?php

namespace Drupal\dashboards\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Dashboard plugins.
 */
abstract class DashboardBase extends PluginBase implements DashboardInterface, ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('dashboards.cache')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CacheBackendInterface $cache_backend) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->cache = $cache_backend;
  }

  /**
   * Cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Build render array.
   *
   * @param array $configuration
   *   Plugin configuration.
   *
   * @return array
   *   Return render array.
   */
  abstract public function buildRenderArray(array $configuration): array;

  /**
   * Build render array.
   *
   * @param array $form
   *   Default form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Default form.
   * @param array $configuration
   *   Configuration.
   *
   * @return array
   *   Return form array.
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state, array $configuration): array {
    return $form;
  }

  /**
   * Validate settings form.
   *
   * @param array $form
   *   Default form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Default form.
   * @param array $configuration
   *   Configuration.
   */
  public function validateForm(array $form, FormStateInterface $form_state, array $configuration): void {

  }

  /**
   * Validate settings form.
   *
   * @param array $form
   *   Default form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Default form.
   * @param array $configuration
   *   Configuration.
   */
  public function massageFormValues(array $form, FormStateInterface $form_state, array $configuration): void {

  }

  /**
   * Get cache for cid.
   *
   * @param string $cid
   *   Cache id.
   *
   * @return mixed
   *   Return cache data.
   */
  protected function getCache(string $cid) {
    return $this->cache->get($this->getPluginId() . ':' . $cid);
  }

  /**
   * Set a new cache entry. Cache is prefixed by plugin ID.
   *
   * @param string $cid
   *   Cache id.
   * @param mixed $data
   *   Data to cache.
   * @param int $expire
   *   Expire data. Default to 3600.
   * @param array $tags
   *   Tags for invalidation.
   */
  protected function setCache(string $cid, $data, int $expire = 3600, array $tags = []): void {
    $this->cache->set($this->getPluginId() . ':' . $cid, $data, $expire, $tags);
  }

}
