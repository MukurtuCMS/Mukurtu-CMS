<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Menu;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Drupal\mukurtu_core\Form\EnableMultilingualForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dashboard link that hides itself once multilingual is enabled.
 */
class EnableMultilingualMenuLink extends MenuLinkDefault {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    StaticMenuLinkOverridesInterface $static_override,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $static_override);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu_link.static.overrides'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    return !$this->moduleHandler->moduleExists(EnableMultilingualForm::MODULE);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return array_merge(parent::getCacheTags(), ['config:core.extension']);
  }

}
