<?php

namespace Drupal\mukurtu_search\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\Event\GatheringPluginInfoEvent;

/**
 * Mukurtu Search event subscriber.
 */
class GatheringDatasourcesSubscriber implements EventSubscriberInterface {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * Kernel response event handler.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   Response event.
   */
  public function alterDatasources(GatheringPluginInfoEvent $event) {
    $definitions = &$event->getDefinitions();
    // Attach custom search api datasource plugin for flagging entities.
    if (isset($definitions['entity:flagging'])) {
      $definitions['entity:flagging']['class'] = "Drupal\mukurtu_search\Plugin\search_api\datasource\FlaggingContentEntity";
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      SearchApiEvents::GATHERING_DATA_SOURCES => ['alterDatasources'],
    ];
  }

}
