<?php

declare(strict_types=1);

namespace Drupal\mukurtu_multilingual\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mukurtu_multilingual\TranslationStatus\TranslationStatusTracker;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Recalculates stored translation status for a batch of entities.
 *
 * Filled by TranslationStatusTracker::queueRebuild() when a language or the
 * translation settings change.
 */
#[QueueWorker(
  id: TranslationStatusTracker::QUEUE,
  title: new TranslatableMarkup('Translation status rebuild'),
  cron: ['time' => 60],
)]
class TranslationStatusRebuildWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected TranslationStatusTracker $tracker,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('mukurtu_multilingual.translation_status_tracker'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $this->tracker->refreshMultiple($data['entity_type'], $data['ids']);
  }

}
