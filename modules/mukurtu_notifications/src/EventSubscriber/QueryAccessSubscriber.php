<?php

namespace Drupal\mukurtu_notifications\EventSubscriber;

use Drupal\entity\QueryAccess\ConditionGroup;
use Drupal\entity\QueryAccess\QueryAccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class QueryAccessSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    return [
      'entity.query_access' => 'onQueryAccess',
      'entity.query_access.message' => 'onQueryAccess',
      'entity.query_access.message_template' => 'onQueryAccess',
    ];
  }

  public function onQueryAccess(QueryAccessEvent $event) {
    $conditions = $event->getConditions();
    $conditions->alwaysFalse();
  }
}
