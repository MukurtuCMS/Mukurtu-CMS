<?php

namespace Drupal\mukurtu_multipage_items\EventSubscriber;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\og\Event\PermissionEventInterface;
use Drupal\og\GroupPermission;
use Drupal\og\OgRoleInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscribers for multipage items.
 */
class MukurtuMultipageItemEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      PermissionEventInterface::EVENT_NAME => [
        ['provideDefaultMukurtuOgPermissions'],
      ],
    ];
  }

  /**
   * Provides default OG permissions.
   *
   * @param \Drupal\og\Event\PermissionEventInterface $event
   *   The OG permission event.
   */
  public function provideDefaultMukurtuOgPermissions(PermissionEventInterface $event) {
    if ($event->getGroupEntityTypeId() == 'protocol') {
      $event->setPermissions([
        new GroupPermission([
          'name' => 'administer multipage item',
          'title' => $this->t('Administer multipage items'),
          'description' => $this->t('Users may manage multipage items in this group.'),
          'default roles' => [OgRoleInterface::ADMINISTRATOR],
          'restrict access' => FALSE,
        ]),
      ]);
    }
  }

}
