<?php

declare(strict_types=1);

namespace Drupal\mukurtu_setup\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\mukurtu_setup\SiteSetupTaskManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Site Setup Checklist dashboard block.
 *
 * @Block(
 *   id = "mukurtu_setup_checklist",
 *   admin_label = @Translation("Setup Checklist"),
 *   category = "Mukurtu CMS"
 * )
 */
class SiteSetupChecklistBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected SiteSetupTaskManager $taskManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('mukurtu_setup.task_manager'),
    );
  }

  public function build(): array {
    $cache = [
      'tags' => ['config:system.site', 'community_list', 'protocol_list', 'taxonomy_term_list', 'block_content_list', 'mukurtu_setup:tasks'],
      'contexts' => ['user.roles'],
    ];
    $counts = $this->taskManager->getCounts();
    if ($counts['total'] > 0 && $counts['complete'] === $counts['total']) {
      return ['#cache' => $cache];
    }
    return [
      '#theme' => 'mukurtu_setup_checklist_block',
      '#total' => $counts['total'],
      '#complete' => $counts['complete'],
      '#setup_url' => Url::fromRoute('mukurtu_setup.setup_page')->toString(),
      '#cache' => $cache,
    ];
  }

}
