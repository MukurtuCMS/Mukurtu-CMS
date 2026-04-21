<?php

declare(strict_types=1);

namespace Drupal\rebuild_cache_access\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Security\Attribute\TrustedCallback;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a rebuild cache navigation block.
 *
 * This block is available for use in the Drupal Core Navigation module.
 */
#[Block(
  id: 'rebuild_cache_access_navigation',
  admin_label: new TranslatableMarkup('Rebuild cache'),
)]
class RebuildCacheAccessNavigationBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Creates a Rebuild Cache Access Navigation Block instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected AccountInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    if ($this->currentUser->hasPermission('rebuild cache access')) {
      return [
        '#create_placeholder' => TRUE,
        '#lazy_builder' => [
          static::class . '::buildRebuildCache',
          [$this->configuration['label']],
        ],
        '#cache' => [
          'keys' => ['navigation_rebuild_cache_block'],
          'contexts' => ['user.permissions'],
        ],
      ];
    }

    // No block needed when no access.
    return [
      '#cache' => [
        'contexts' => ['user.permissions'],
      ],
    ];
  }

  /**
   * Lazy builder callback.
   */
  #[TrustedCallback]
  public static function buildRebuildCache(string $label): array {
    $build = [];
    if (\Drupal::currentUser()->hasPermission('rebuild cache access')) {
      $build['rebuild_cache_access'] = [
        '#title' => '',
        '#theme' => 'navigation_menu',
        '#menu_name' => 'link',
        '#items' => [
          [
            'title' => $label,
            'url' => Url::fromRoute('rebuild_cache_access.rebuild_cache'),
            'class' => 'system-admin-config',
            'icon' => [
              'icon_id' => 'system-admin-config',
            ],
          ],
        ],
      ];
    }

    $build['#title'] = $label;
    $build['#cache']['contexts'][] = 'user.permissions';
    return $build;
  }

}
