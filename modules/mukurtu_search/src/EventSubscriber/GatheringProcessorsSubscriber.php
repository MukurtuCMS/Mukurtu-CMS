<?php

declare(strict_types=1);

namespace Drupal\mukurtu_search\EventSubscriber;

use Drupal\mukurtu_search\Plugin\search_api\processor\KeepUnmappedTransliteration;
use Drupal\search_api\Event\GatheringPluginInfoEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Swaps in Mukurtu's version of the transliteration processor.
 *
 * Every index that uses Search API's "transliteration" processor, including
 * ones a site builds itself, gets the version that keeps characters with no
 * transliteration instead of replacing them with "?".
 */
class GatheringProcessorsSubscriber implements EventSubscriberInterface {

  /**
   * Replaces the class of the transliteration processor.
   *
   * @param \Drupal\search_api\Event\GatheringPluginInfoEvent $event
   *   The processor info gathering event.
   */
  public function alterProcessors(GatheringPluginInfoEvent $event): void {
    $definitions = &$event->getDefinitions();
    if (isset($definitions['transliteration'])) {
      $definitions['transliteration']['class'] = KeepUnmappedTransliteration::class;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::GATHERING_PROCESSORS => ['alterProcessors'],
    ];
  }

}
