<?php

declare(strict_types=1);

namespace Drupal\mukurtu_setup\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\mukurtu_setup\SiteSetupTaskManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the full site setup checklist page.
 */
class SiteSetupController extends ControllerBase {

  public function __construct(protected SiteSetupTaskManager $taskManager) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('mukurtu_setup.task_manager'));
  }

  public function page(): array {
    $groups = $this->taskManager->getTaskGroups();
    $dismissed = [];

    $group_labels = [
      SiteSetupTaskManager::GROUP_REQUIRED => $this->t('Required'),
      SiteSetupTaskManager::GROUP_RECOMMENDED => $this->t('Recommended'),
    ];

    $rendered_groups = [];
    foreach ($groups as $group_key => $tasks) {
      $rendered_tasks = [];
      foreach ($tasks as $task) {
        if ($this->taskManager->isDismissed($task->getId())) {
          $dismissed[$task->getId()] = $this->buildTaskRow($task);
          continue;
        }
        $rendered_tasks[$task->getId()] = $this->buildTaskRow($task);
      }
      if (!empty($rendered_tasks)) {
        $rendered_groups[$group_key] = [
          'label' => $group_labels[$group_key],
          'tasks' => $rendered_tasks,
        ];
      }
    }

    $counts = $this->taskManager->getCounts();

    return [
      '#theme' => 'mukurtu_setup_checklist',
      '#groups' => $rendered_groups,
      '#dismissed' => $dismissed,
      '#all_complete' => $counts['total'] > 0 && $counts['complete'] === $counts['total'],
      '#cache' => [
        'tags' => [
          'config:system.site',
          'community_list',
          'protocol_list',
          'taxonomy_term_list',
          'block_content_list',
          'mukurtu_setup:tasks',
        ],
        'contexts' => ['user.roles'],
      ],
    ];
  }

  public function access(AccountInterface $account): AccessResult {
    $roles = $account->getRoles();
    $allowed = !empty(array_intersect(['administrator', 'mukurtu_manager'], $roles));
    return AccessResult::allowedIf($allowed)->addCacheContexts(['user.roles']);
  }

  private function buildTaskRow(object $task): array {
    $complete = $this->taskManager->isComplete($task->getId());
    $dismissed = $this->taskManager->isDismissed($task->getId());

    $row = [
      'id' => $task->getId(),
      'label' => $task->getLabel(),
      'description' => $task->getDescription(),
      'group' => $task->getGroup(),
      'complete' => $complete,
      'dismissed' => $dismissed,
      'can_auto_detect' => $task->canAutoDetect(),
      'action_url' => $task->getActionUrl(),
      'action_label' => $task->getActionLabel(),
    ];

    // Build action links.
    if ($dismissed) {
      $row['restore_url'] = $this->buildCsrfUrl('mukurtu_setup.restore_task', $task->getId());
    }
    elseif ($complete && !$task->canAutoDetect()) {
      $row['uncomplete_url'] = $this->buildCsrfUrl('mukurtu_setup.uncomplete_task', $task->getId());
    }
    elseif (!$complete) {
      if (!$task->canAutoDetect()) {
        $row['complete_url'] = $this->buildCsrfUrl('mukurtu_setup.complete_task', $task->getId());
      }
      if ($task->getGroup() !== SiteSetupTaskManager::GROUP_REQUIRED || $task->isDismissible()) {
        $row['dismiss_url'] = $this->buildCsrfUrl('mukurtu_setup.dismiss_task', $task->getId());
      }
    }

    return $row;
  }

  private function buildCsrfUrl(string $route, string $taskId): string {
    $url = Url::fromRoute($route, ['task_id' => $taskId]);
    $token = \Drupal::csrfToken()->get($url->getInternalPath());
    $url->setOption('query', ['token' => $token]);
    return $url->toString();
  }

}
