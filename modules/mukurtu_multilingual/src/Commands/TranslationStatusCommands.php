<?php

declare(strict_types=1);

namespace Drupal\mukurtu_multilingual\Commands;

use Drupal\mukurtu_multilingual\TranslationStatus\TranslationStatusTracker;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for the translation status pages.
 */
class TranslationStatusCommands extends DrushCommands {

  public function __construct(
    protected TranslationStatusTracker $tracker,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('mukurtu_multilingual.translation_status_tracker'),
    );
  }

  /**
   * Recalculates every stored translation status straight away.
   *
   * Normally statuses update as content is saved, and a change of language
   * or translation settings rebuilds them on cron. This is for recovery, or
   * for when the pages need to be right before cron next runs.
   */
  #[CLI\Command(name: 'mukurtu:translation-status-rebuild')]
  #[CLI\Help(description: 'Rebuild the stored translation status for all translatable content.')]
  public function rebuild(): void {
    $count = 0;
    foreach ($this->tracker->trackedEntityIds() as $entity_type_id => $ids) {
      foreach (array_chunk($ids, 50) as $chunk) {
        $this->tracker->refreshMultiple($entity_type_id, $chunk);
        $count += count($chunk);
      }
    }
    $this->logger()->success(dt('Recorded translation status for @count items.', ['@count' => $count]));
  }

}
