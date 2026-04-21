<?php

namespace Drupal\dashboards\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides block plugin definitions for dashboard blocks.
 */
class DashboardBlock extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * Plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $manager;

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $definitions = $this->manager->getDefinitions();

    foreach ($definitions as $id => $definition) {
      $this->derivatives['dashboard:' . $id] = $base_plugin_definition;
      $this->derivatives['dashboard:' . $id]['admin_label'] = $this->t('@display_name', [
        '@display_name' => $definition['label'],
      ]);
      $this->derivatives['dashboard:' . $id]['category'] = $definition['category'];
    }
    return $this->derivatives;
  }

  /**
   * Constructs new DashboardBlock.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $plugin_manager
   *   The entity type manager.
   */
  public function __construct(PluginManagerInterface $plugin_manager) {
    $this->manager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('plugin.manager.dashboard')
    );
  }

}
