<?php

declare(strict_types=1);

namespace Drupal\mukurtu_setup\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_setup\SiteSetupTaskManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Handles task state mutations: dismiss, restore, complete, uncomplete.
 */
class SiteSetupDismissController extends ControllerBase {

  public function __construct(protected SiteSetupTaskManager $taskManager) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('mukurtu_setup.task_manager'));
  }

  public function dismiss(string $task_id): RedirectResponse {
    $tasks = $this->taskManager->getTasks();
    $ids = array_map(fn($t) => $t->getId(), $tasks);
    if (in_array($task_id, $ids, TRUE)) {
      $this->taskManager->dismiss($task_id);
    }
    return $this->redirect('mukurtu_setup.setup_page');
  }

  public function restore(string $task_id): RedirectResponse {
    $tasks = $this->taskManager->getTasks();
    $ids = array_map(fn($t) => $t->getId(), $tasks);
    if (in_array($task_id, $ids, TRUE)) {
      $this->taskManager->restore($task_id);
    }
    return $this->redirect('mukurtu_setup.setup_page');
  }

  public function complete(string $task_id): RedirectResponse {
    $tasks = $this->taskManager->getTasks();
    $ids = array_map(fn($t) => $t->getId(), $tasks);
    if (in_array($task_id, $ids, TRUE)) {
      $this->taskManager->markComplete($task_id);
    }
    return $this->redirect('mukurtu_setup.setup_page');
  }

  public function uncomplete(string $task_id): RedirectResponse {
    $tasks = $this->taskManager->getTasks();
    $ids = array_map(fn($t) => $t->getId(), $tasks);
    if (in_array($task_id, $ids, TRUE)) {
      $this->taskManager->markIncomplete($task_id);
    }
    return $this->redirect('mukurtu_setup.setup_page');
  }

  public function access(AccountInterface $account): AccessResult {
    $roles = $account->getRoles();
    $allowed = !empty(array_intersect(['administrator', 'mukurtu_manager'], $roles));
    return AccessResult::allowedIf($allowed)->addCacheContexts(['user.roles']);
  }

}
