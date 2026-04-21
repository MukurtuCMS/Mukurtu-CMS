<?php

namespace Drupal\search_api_test_views;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides an event listener for testing purposes.
 *
 * @see \Drupal\Tests\search_api\Functional\ViewsTest
 */
class EventListener implements EventSubscriberInterface {

  /**
   * Drupal state key to toggle printing of the query to the page.
   */
  const STATE_PRINT_QUERY = 'search_api_test_views.print_query';

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::QUERY_PRE_EXECUTE => 'queryAlter',
      SearchApiEvents::QUERY_PRE_EXECUTE . '.weather' => 'queryTagAlter',
    ];
  }

  /**
   * Reacts to the query alter event.
   *
   * Prints the executed search query to the page, if the
   * "search_api_test_views.print_query" Drupal state key is enabled.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The event.
   */
  public function queryAlter(QueryPreExecuteEvent $event): void {
    if (\Drupal::state()->get(static::STATE_PRINT_QUERY)) {
      $message = new TranslatableMarkup('<div style="white-space: pre-wrap; background-color: #333; color: #ddd">@q</div>', ['@q' => (string) $event->getQuery()]);
      \Drupal::messenger()->addStatus($message);
    }
  }

  /**
   * Reacts to the query TAG alter event.
   */
  public function queryTagAlter(): void {
    $this->messenger->addStatus('Sunshine');
  }

}
