<?php

declare(strict_types=1);

namespace Drupal\mukurtu_setup\Menu;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Drupal\mukurtu_setup\SiteSetupTaskManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Menu link that hides itself once all setup tasks are complete.
 */
class SetupMenuLink extends MenuLinkDefault {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    StaticMenuLinkOverridesInterface $static_override,
    protected SiteSetupTaskManager $taskManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $static_override);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu_link.static.overrides'),
      $container->get('mukurtu_setup.task_manager'),
    );
  }

  public function isEnabled(): bool {
    $counts = $this->taskManager->getCounts();
    return !($counts['total'] > 0 && $counts['complete'] === $counts['total']);
  }

  public function getCacheTags(): array {
    return array_merge(parent::getCacheTags(), [
      'community_list',
      'protocol_list',
      'taxonomy_term_list',
      'block_content_list',
      'mukurtu_setup:tasks',
    ]);
  }

}
