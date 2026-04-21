<?php

declare(strict_types=1);

namespace Drupal\actions_permissions\EventSubscriber;

use Drupal\Core\Session\AccountInterface;
use Drupal\views_bulk_operations\ActionAlterDefinitionsEvent;
use Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines module event subscriber class.
 *
 * Alters actions to make use of permissions created by the module.
 */
final class ActionsPermissionsEventSubscriber implements EventSubscriberInterface {

  // Subscribe to the VBO event with low priority
  // to let other modules alter requirements first.
  private const PRIORITY = -999;

  /**
   * Constructor.
   */
  public function __construct(
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ViewsBulkOperationsActionManager::ALTER_ACTIONS_EVENT => [
        ['alterActions', self::PRIORITY],
      ],
    ];
  }

  /**
   * Alter the actions' definitions.
   */
  public function alterActions(ActionAlterDefinitionsEvent $event): void {

    // Don't alter definitions if this is invoked by the
    // own permissions creating method.
    if (\array_key_exists('skip_actions_permissions', $event->alterParameters)) {
      return;
    }

    foreach ($event->definitions as $action_id => $definition) {

      // Only process actions that don't define their own requirements.
      if (\array_key_exists('requirements', $definition) && \count($definition['requirements']) > 0) {
        continue;
      }

      $permission_id = 'execute ' . $definition['id'];
      if ($definition['type'] === '') {
        $permission_id .= ' all';
      }
      else {
        $permission_id .= ' ' . $definition['type'];
      }
      if (!$this->currentUser->hasPermission($permission_id)) {
        unset($event->definitions[$action_id]);
      }
    }
  }

}
